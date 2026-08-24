<?php

namespace App;

use App\Models\GpzuData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GpzuService
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public function findGpzuFile(array $attachments): ?array
    {
        foreach ($attachments as $attachment) {
            $fileName = $attachment['fileName'] ?? '';
            if (str_contains(mb_strtolower($fileName), 'гпзу')) {
                return $attachment;
            }
        }

        return null;
    }

    public function getDataForLot(string $lotId): ?GpzuData
    {
        return GpzuData::where('lot_id', $lotId)->first();
    }

    /**
     * Process ГПЗУ: download via DocumentService, OCR, find page numbers.
     */
    public function processWithProgress(string $lotId, array $gpzuFile): array
    {
        $status = new GpzuProcessingStatus($lotId);

        $existing = $this->getDataForLot($lotId);
        if ($existing) {
            return ['success' => true, 'data' => $existing];
        }

        $missing = GpzuProcessingStatus::checkRequirements();
        if (! empty($missing)) {
            $errorMsg = 'Отсутствуют системные утилиты: '.implode(', ', $missing);
            Log::error('ГПЗУ: Missing requirements', ['missing' => $missing]);

            return ['success' => false, 'error' => $errorMsg];
        }

        $fileId = $gpzuFile['fileId'];
        $fileName = $gpzuFile['fileName'];

        // Step 1: Download via DocumentService (single storage location)
        $status->setStep('download', 'Скачивание файла...');
        $pdfPath = $this->documentService->getFileForPreview($fileId, $fileName);
        if (! $pdfPath || ! file_exists($pdfPath)) {
            $errorMsg = 'Не удалось скачать файл ГПЗУ с сервера торгов';
            Log::error('ГПЗУ: Failed to download PDF', ['file_id' => $fileId]);

            return ['success' => false, 'error' => $errorMsg];
        }

        // Step 2: Count pages
        $status->setStep('count', 'Подсчёт страниц...');
        $pageCount = $this->getPdfPageCount($pdfPath);
        if ($pageCount <= 0) {
            $errorMsg = 'Не удалось определить количество страниц PDF';
            Log::error('ГПЗУ: Failed to get page count', ['file_id' => $fileId]);

            return ['success' => false, 'error' => $errorMsg];
        }

        // Step 3: OCR
        $status->setStep('ocr', 'Распознавание текста (OCR)...', 0, $pageCount);
        $pages = $this->ocrAllPages($pdfPath, $status);
        if (empty($pages)) {
            $errorMsg = 'OCR не распознал текст ни на одной странице.';
            Log::error('ГПЗУ: OCR produced no results', ['file_id' => $fileId]);

            return ['success' => false, 'error' => $errorMsg];
        }

        $totalChars = array_sum(array_map('mb_strlen', $pages));
        if ($totalChars < 100) {
            $errorMsg = 'Распознано слишком мало текста ('.$totalChars.' символов).';
            Log::warning('ГПЗУ: OCR produced very little text', ['total_chars' => $totalChars]);

            return ['success' => false, 'error' => $errorMsg];
        }

        // Step 4: Parse — find page numbers
        $status->setStep('parse', 'Анализ содержимого...');
        $permittedUses = $this->parsePermittedUses($pages);
        $drawingPage = $this->findDrawingPage($pages);
        $appendixPage = $this->findFirstAppendixPage($pages);

        // Step 5: Save
        $status->setStep('save', 'Сохранение результатов...');
        $data = GpzuData::create([
            'lot_id' => $lotId,
            'file_id' => $fileId,
            'file_name' => $fileName,
            'permitted_uses' => $permittedUses,
            'appendix_page' => $appendixPage,
            'drawing_page' => $drawingPage,
        ]);

        $status->setComplete([
            'id' => $data->id,
            'lot_id' => $data->lot_id,
        ]);

        return ['success' => true, 'data' => $data];
    }

    private function ocrAllPages(string $pdfPath, GpzuProcessingStatus $status): array
    {
        $pageCount = $this->getPdfPageCount($pdfPath);
        if ($pageCount <= 0) {
            return [];
        }

        $tempDir = storage_path('app/gpzu');
        File::makeDirectory($tempDir, 0755, true, true);
        $ocrDir = $tempDir.'/ocr_'.md5($pdfPath);
        File::makeDirectory($ocrDir, 0755, true, true);

        $dpi = config('gpzu.ocr_dpi', 300);
        $lang = config('gpzu.ocr_lang', 'rus');

        $prefix = $ocrDir.'/page';
        exec(sprintf(
            'pdftoppm -f 1 -l %d -png -r %d %s %s 2>&1',
            $pageCount,
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($prefix),
        ));

        $pages = [];
        for ($i = 1; $i <= $pageCount; $i++) {
            $imagePath = sprintf('%s/page-%02d.png', $ocrDir, $i);
            if (! file_exists($imagePath)) {
                continue;
            }

            $status->setStep('ocr', "Распознавание страницы {$i}/{$pageCount}...", $i, $pageCount);
            $text = $this->ocrPage($imagePath, $lang);
            $pages[$i] = $text;

            @unlink($imagePath);
        }

        @rmdir($ocrDir);

        return $pages;
    }

    private function ocrPage(string $imagePath, string $lang): string
    {
        $command = sprintf(
            'tesseract %s - -l %s 2>/dev/null',
            escapeshellarg($imagePath),
            escapeshellarg($lang),
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0 ? implode("\n", $output) : '';
    }

    private function getPdfPageCount(string $pdfPath): int
    {
        exec(sprintf('pdfinfo %s 2>/dev/null', escapeshellarg($pdfPath)), $output, $returnCode);

        if ($returnCode !== 0) {
            return 0;
        }

        foreach ($output as $line) {
            if (preg_match('/^Pages:\s+(\d+)$/', trim($line), $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function parsePermittedUses(array $pages): ?array
    {
        $fullText = '';
        foreach ($pages as $text) {
            $fullText .= $text."\n\n";
        }

        $pattern = '/основные\s+виды\s+разрешенного\s+использования/i';
        if (! preg_match($pattern, $fullText, $startMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $startPos = $startMatch[0][1] + strlen($startMatch[0][0]);

        $endPattern = '/условно\s+разрешенные\s+виды\s+использования/i';
        if (preg_match($endPattern, $fullText, $endMatch, PREG_OFFSET_CAPTURE, $startPos)) {
            $endPos = $endMatch[0][1];
        } else {
            $endPos = strlen($fullText);
        }

        $section = substr($fullText, $startPos, $endPos - $startPos);

        $items = [];
        if (preg_match_all('/\d+[.)\s]\s*(.+?)(?:\s+(\d+\.\d+[a-z]?(?:\*{0,2})))/mu', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $code = trim($match[2]);
                if ($name !== '' && mb_strlen($name) > 2) {
                    $items[] = [
                        'name' => $name,
                        'code' => $code,
                    ];
                }
            }
        }

        if (empty($items)) {
            $lines = explode("\n", $section);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || mb_strlen($line) < 5) {
                    continue;
                }
                if (preg_match('/^(.+?)\s+(\d+\.\d+[a-z]?(?:\*{0,2}))\s*$/u', $line, $m)) {
                    $name = trim($m[1]);
                    if ($name !== '' && mb_strlen($name) > 2) {
                        $items[] = [
                            'name' => $name,
                            'code' => $m[2],
                        ];
                    }
                }
            }
        }

        return ! empty($items) ? $items : null;
    }

    private function findFirstAppendixPage(array $pages): ?int
    {
        ksort($pages);
        $foundAppendix = false;

        foreach ($pages as $pageNum => $text) {
            if (! $foundAppendix) {
                if (str_contains(mb_strtolower($text), 'приложения')) {
                    $foundAppendix = true;
                }

                continue;
            }

            return $pageNum;
        }

        return null;
    }

    private function findDrawingPage(array $pages): ?int
    {
        foreach ($pages as $pageNum => $text) {
            $lowerText = mb_strtolower($text);
            if (str_contains($lowerText, 'чертеж градостроительного плана')) {
                return $pageNum;
            }
        }

        return null;
    }
}

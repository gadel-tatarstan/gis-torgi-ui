<?php

namespace App;

use App\Models\GpzuData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GpzuService
{
    private const TORGİ_FILE_URL = 'https://torgi.gov.ru/new/file-store/v1/';

    private const UTILITY_NETWORK_TYPES = [
        'Теплоснабжение',
        'Водоотведение',
        'Холодное водоснабжение',
        'Электроснабжение',
    ];

    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    /**
     * Find ГПЗУ file in lot attachments.
     */
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

    /**
     * Check if ГПЗУ data already exists for a lot.
     */
    public function getDataForLot(string $lotId): ?GpzuData
    {
        return GpzuData::where('lot_id', $lotId)->first();
    }

    /**
     * Process ГПЗУ file with progress reporting.
     *
     * @return array{success: bool, data?: GpzuData, error?: string}
     */
    public function processWithProgress(string $lotId, array $gpzuFile): array
    {
        $status = new GpzuProcessingStatus($lotId);

        // Check if already processed
        $existing = $this->getDataForLot($lotId);
        if ($existing) {
            return ['success' => true, 'data' => $existing];
        }

        // Check system requirements
        $missing = GpzuProcessingStatus::checkRequirements();
        if (! empty($missing)) {
            $errorMsg = 'Отсутствуют системные утилиты: '.implode(', ', $missing);
            Log::error('ГПЗУ: Missing requirements', ['missing' => $missing]);

            return ['success' => false, 'error' => $errorMsg];
        }

        $fileId = $gpzuFile['fileId'];
        $fileName = $gpzuFile['fileName'];

        // Step 1: Download
        $status->setStep('download', 'Скачивание файла...');
        $pdfPath = $this->downloadPdf($fileId, $fileName);
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
            $errorMsg = 'OCR не распознал текст ни на одной странице. Возможно, файл не является сканом.';
            Log::error('ГПЗУ: OCR produced no results', ['file_id' => $fileId]);

            return ['success' => false, 'error' => $errorMsg];
        }

        // Check if OCR produced meaningful content
        $totalChars = array_sum(array_map('mb_strlen', $pages));
        if ($totalChars < 100) {
            $errorMsg = 'Распознано слишком мало текста ('.$totalChars.' символов). Файл может быть повреждён.';
            Log::warning('ГПЗУ: OCR produced very little text', [
                'file_id' => $fileId,
                'total_chars' => $totalChars,
            ]);

            return ['success' => false, 'error' => $errorMsg];
        }

        // Step 4: Parse
        $status->setStep('parse', 'Анализ содержимого...');
        $permittedUses = $this->parsePermittedUses($pages);
        $utilityTables = $this->parseUtilityTables($pages);
        $gasPage = $this->findGasPage($pages);
        $drawingPage = $this->findDrawingPage($pages);

        // Step 5: Save
        $status->setStep('save', 'Сохранение результатов...');
        $data = GpzuData::create([
            'lot_id' => $lotId,
            'file_id' => $fileId,
            'file_name' => $fileName,
            'permitted_uses' => $permittedUses,
            'utility_tables' => $utilityTables,
            'gas_page' => $gasPage,
            'drawing_page' => $drawingPage,
        ]);

        // Step 6: Done
        $status->setComplete([
            'id' => $data->id,
            'lot_id' => $data->lot_id,
        ]);

        return ['success' => true, 'data' => $data];
    }

    /**
     * Download PDF from torgi.gov.ru.
     */
    private function downloadPdf(string $fileId, string $fileName): ?string
    {
        $storagePath = config('gpzu.temp_dir', storage_path('app/gpzu'));
        File::makeDirectory($storagePath, 0755, true, true);

        $localPath = $storagePath.'/'.$fileId.'.pdf';

        if (File::exists($localPath)) {
            return $localPath;
        }

        try {
            $response = Http::timeout(120)
                ->withOptions(['verify' => false])
                ->get(self::TORGİ_FILE_URL.$fileId);

            if ($response->failed()) {
                Log::error('ГПЗУ: Download failed', [
                    'file_id' => $fileId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            File::put($localPath, $response->body());

            return $localPath;
        } catch (\Exception $e) {
            Log::error('ГПЗУ: Download exception', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * OCR all pages of the PDF with progress reporting.
     */
    private function ocrAllPages(string $pdfPath, GpzuProcessingStatus $status): array
    {
        $pageCount = $this->getPdfPageCount($pdfPath);
        if ($pageCount <= 0) {
            return [];
        }

        $tempDir = config('gpzu.temp_dir', storage_path('app/gpzu'));
        $ocrDir = $tempDir.'/ocr_'.md5($pdfPath);
        File::makeDirectory($ocrDir, 0755, true, true);

        $dpi = config('gpzu.ocr_dpi', 300);
        $lang = config('gpzu.ocr_lang', 'rus');

        // Convert all pages to images
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

    /**
     * OCR a single page image.
     */
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

    /**
     * Get PDF page count using pdfinfo.
     */
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

    /**
     * Parse permitted uses from OCR text.
     */
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

    /**
     * Parse utility connection tables from OCR text.
     */
    private function parseUtilityTables(array $pages): ?array
    {
        $tables = [];
        $foundAppendix = false;

        ksort($pages);

        foreach ($pages as $pageNum => $text) {
            $lowerText = mb_strtolower($text);

            if (str_contains($lowerText, 'приложения')) {
                $foundAppendix = true;

                continue;
            }

            if (! $foundAppendix) {
                continue;
            }

            $networkType = $this->detectNetworkType($text);
            if ($networkType === null) {
                continue;
            }

            $connectionAvailable = $this->extractConnectionInfo($text);
            $maxLoad = $this->extractMaxLoad($text);

            $tables[] = [
                'network_type' => $networkType,
                'connection_available' => $connectionAvailable,
                'max_load' => $maxLoad,
                'page' => $pageNum,
            ];
        }

        return ! empty($tables) ? $tables : null;
    }

    private function detectNetworkType(string $text): ?string
    {
        $lowerText = mb_strtolower($text);

        foreach (self::UTILITY_NETWORK_TYPES as $type) {
            if (str_contains($lowerText, mb_strtolower($type))) {
                return $type;
            }
        }

        return null;
    }

    private function extractConnectionInfo(string $text): ?string
    {
        $pattern = '/сведения\s+о\s+наличии\s+или\s+об\s+отсутствии\s+технической\s+возможности\s+подключения\s*\n?\s*(.+)/iu';
        if (preg_match($pattern, mb_strtolower($text), $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/сведения\s+о\s+наличии.*?подключения\s*\n\s*(Отсутствует|Имеется)/ius', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractMaxLoad(string $text): ?string
    {
        $pattern = '/сведения\s+о\s+максимальной\s+нагрузке\s+в\s+возможных\s+точках\s*\n?\s*подключения.*?\n?\s*(.+)/iu';
        if (preg_match($pattern, mb_strtolower($text), $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/максимальной\s+нагрузке.*?подключения.*?\n\s*(Отсутствует|[\s\S]{5,80}?)(?:\n|Срок)/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function findGasPage(array $pages): ?int
    {
        foreach ($pages as $pageNum => $text) {
            if (str_contains(mb_strtolower($text), 'газоснабжение')) {
                return $pageNum;
            }
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

    public function extractPdfPage(string $pdfPath, int $pageNumber): ?string
    {
        $storagePath = config('gpzu.temp_dir', storage_path('app/gpzu'));
        File::makeDirectory($storagePath, 0755, true, true);

        $outputPath = $storagePath.'/page_'.md5($pdfPath).'_'.$pageNumber.'.pdf';

        if (File::exists($outputPath)) {
            return $outputPath;
        }

        $command = sprintf(
            'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>&1',
            $pageNumber,
            $pageNumber,
            escapeshellarg($outputPath),
            escapeshellarg($pdfPath),
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || ! File::exists($outputPath)) {
            Log::error('ГПЗУ: Failed to extract PDF page', [
                'page' => $pageNumber,
                'output' => implode("\n", $output),
            ]);

            return null;
        }

        return $outputPath;
    }

    public function getLocalPdfPath(string $fileId): ?string
    {
        $storagePath = config('gpzu.temp_dir', storage_path('app/gpzu'));
        $path = $storagePath.'/'.$fileId.'.pdf';

        return File::exists($path) ? $path : null;
    }
}

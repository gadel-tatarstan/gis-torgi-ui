<?php

namespace App;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentService
{
    private const STORAGE_DIR = 'app/documents';

    private const TORGİ_FILE_URL = 'https://torgi.gov.ru/new/file-store/v1/';

    private const OFFICE_EXTENSIONS = [
        'docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt',
        'odt', 'ods', 'odp', 'rtf', 'csv', 'txt',
    ];

    /**
     * Get a file for preview — returns local PDF path if available,
     * otherwise downloads, converts (if needed), and caches.
     */
    public function getFileForPreview(string $fileId, string $fileName): ?string
    {
        $storagePath = $this->getStoragePath();
        $cachedPdf = $storagePath.'/'.$fileId.'.pdf';
        $cachedOriginal = $storagePath.'/'.$fileId.'.'.pathinfo($fileName, PATHINFO_EXTENSION);

        // 1. Return cached PDF if exists
        if (File::exists($cachedPdf)) {
            return $cachedPdf;
        }

        // 2. Check if it's already a PDF
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            if (File::exists($cachedOriginal)) {
                return $cachedOriginal;
            }

            return $this->downloadAndCache($fileId, $fileName);
        }

        // 3. Check if it's an office document that needs conversion
        if (in_array($ext, self::OFFICE_EXTENSIONS)) {
            // Return cached original if we already converted it
            if (File::exists($cachedOriginal)) {
                // Re-convert only if PDF doesn't exist
                if (! File::exists($cachedPdf)) {
                    $this->convertToPdf($cachedOriginal, $cachedPdf);
                }

                return $cachedPdf;
            }

            // Download, cache, then convert
            $originalPath = $this->downloadAndCache($fileId, $fileName);
            if ($originalPath) {
                $this->convertToPdf($originalPath, $cachedPdf);

                return $cachedPdf;
            }
        }

        // 4. Non-office file — just download and serve
        return $this->downloadAndCache($fileId, $fileName);
    }

    /**
     * Download file from torgi.gov.ru and save to local storage.
     */
    private function downloadAndCache(string $fileId, string $fileName): ?string
    {
        $storagePath = $this->getStoragePath();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $localPath = $storagePath.'/'.$fileId.'.'.$ext;

        if (File::exists($localPath)) {
            return $localPath;
        }

        try {
            $response = Http::timeout(60)->withOptions(['verify' => false])->get(self::TORGİ_FILE_URL.$fileId);

            if ($response->failed()) {
                Log::error('Document download failed', [
                    'file_id' => $fileId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            File::makeDirectory($storagePath, 0755, true, true);
            File::put($localPath, $response->body());

            return $localPath;
        } catch (\Exception $e) {
            Log::error('Document download exception', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert an office document to PDF using LibreOffice headless.
     */
    private function convertToPdf(string $inputPath, string $outputPdfPath): bool
    {
        $outputDir = pathinfo($outputPdfPath, PATHINFO_DIRNAME);

        try {
            $command = sprintf(
                'libreoffice --headless --norestore --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($inputPath),
            );

            Log::info('Converting document to PDF', ['input' => $inputPath]);

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('LibreOffice conversion failed', [
                    'input' => $inputPath,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);

                return false;
            }

            // LibreOffice outputs file with .pdf extension in the same directory
            $expectedPdf = pathinfo($inputPath, PATHINFO_FILENAME).'.pdf';
            $generatedPdf = $outputDir.'/'.$expectedPdf;

            if (File::exists($generatedPdf) && $generatedPdf !== $outputPdfPath) {
                File::move($generatedPdf, $outputPdfPath);
            }

            Log::info('PDF conversion successful', ['output' => $outputPdfPath]);

            return true;
        } catch (\Exception $e) {
            Log::error('PDF conversion exception', [
                'input' => $inputPath,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getStoragePath(): string
    {
        return storage_path(self::STORAGE_DIR);
    }

    /**
     * Check if a file extension is an office document type.
     */
    public function isOfficeDocument(string $fileName): bool
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($ext, self::OFFICE_EXTENSIONS);
    }

    /**
     * Check if a file is a PDF.
     */
    public function isPdf(string $fileName): bool
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Get the MIME type for a file.
     */
    public function getMimeType(string $filePath): string
    {
        $mime = mime_content_type($filePath);

        return $mime ?: 'application/octet-stream';
    }
}

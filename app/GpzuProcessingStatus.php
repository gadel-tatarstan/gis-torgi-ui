<?php

namespace App;

use Illuminate\Support\Facades\File;

class GpzuProcessingStatus
{
    private string $statusDir;

    private string $statusFile;

    private string $lockFile;

    private string $lotId;

    public function __construct(string $lotId)
    {
        $this->lotId = $lotId;
        $this->statusDir = config('gpzu.temp_dir', storage_path('app/gpzu'));
        $this->statusFile = $this->statusDir.'/status_'.$lotId.'.json';
        $this->lockFile = $this->statusDir.'/lock_'.$lotId;
    }

    /**
     * Check if processing is currently running for this lot.
     */
    public function isProcessing(): bool
    {
        if (! File::exists($this->lockFile)) {
            return false;
        }

        // Stale lock detection (older than 10 minutes)
        $lockAge = time() - File::lastModified($this->lockFile);

        return $lockAge < 600;
    }

    /**
     * Acquire processing lock. Returns false if already locked.
     */
    public function acquireLock(): bool
    {
        File::makeDirectory($this->statusDir, 0755, true, true);

        if ($this->isProcessing()) {
            return false;
        }

        File::put($this->lockFile, (string) getmypid());

        return true;
    }

    /**
     * Release processing lock.
     */
    public function releaseLock(): void
    {
        @unlink($this->lockFile);
    }

    /**
     * Update processing step status.
     */
    public function setStep(string $step, string $message, int $current = 0, int $total = 0): void
    {
        $data = [
            'step' => $step,
            'message' => $message,
            'current' => $current,
            'total' => $total,
            'timestamp' => time(),
        ];

        File::put($this->statusFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Mark processing as completed successfully.
     */
    public function setComplete(array $result): void
    {
        $data = [
            'step' => 'done',
            'message' => 'Обработка завершена',
            'result' => $result,
            'timestamp' => time(),
        ];

        File::put($this->statusFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Mark processing as failed.
     */
    public function setError(string $message): void
    {
        $data = [
            'step' => 'error',
            'message' => $message,
            'timestamp' => time(),
        ];

        File::put($this->statusFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get current status.
     */
    public function getStatus(): ?array
    {
        if (! File::exists($this->statusFile)) {
            return null;
        }

        $content = File::get($this->statusFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Clear status file.
     */
    public function clear(): void
    {
        @unlink($this->statusFile);
    }

    /**
     * Check if Tesseract is available on the system.
     */
    public static function isTesseractAvailable(): bool
    {
        exec('which tesseract 2>/dev/null', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if required system tools are available.
     */
    public static function checkRequirements(): array
    {
        $missing = [];

        $tools = ['tesseract', 'pdftoppm', 'pdfinfo', 'gs'];
        foreach ($tools as $tool) {
            exec("which {$tool} 2>/dev/null", $output, $returnCode);
            if ($returnCode !== 0) {
                $missing[] = $tool;
            }
        }

        // Check Tesseract Russian language
        if (in_array('tesseract', $tools) === false) {
            exec('tesseract --list-langs 2>&1', $langOutput);
            $hasRussian = false;
            foreach ($langOutput as $line) {
                if (trim($line) === 'rus') {
                    $hasRussian = true;
                    break;
                }
            }
            if (! $hasRussian) {
                $missing[] = 'tesseract-rus (язык)';
            }
        }

        return $missing;
    }
}

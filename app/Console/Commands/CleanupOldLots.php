<?php

namespace App\Console\Commands;

use App\Models\Lot;
use App\Models\UserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldLots extends Command
{
    protected $signature = 'app:cleanup-old-lots';

    protected $description = 'Удаление старых лотов и связанных файлов';

    private const DEFAULT_DAYS = 20;

    public function handle(): int
    {
        $days = $this->getDaysToKeep();
        $storagePath = storage_path('app/documents');
        $deletedLots = 0;
        $deletedFiles = 0;

        $oldLots = Lot::where('created_at', '<', now()->subDays($days))->get();

        foreach ($oldLots as $lot) {
            $fileIds = $this->extractFileIds($lot);

            foreach ($fileIds as $fileId) {
                $deletedFiles += $this->deleteFilesByFileId($storagePath, $fileId);
            }

            $lot->delete();
            $deletedLots++;
        }

        $lotWord = self::pluralize($deletedLots, 'лот', 'лота', 'лотов');
        $fileWord = self::pluralize($deletedFiles, 'файл', 'файла', 'файлов');
        $this->info("Удалено {$deletedLots} {$lotWord} (старше {$days} дн.) и {$deletedFiles} {$fileWord}.");

        Log::info('Cleanup old lots completed', [
            'days' => $days,
            'deleted_lots' => $deletedLots,
            'deleted_files' => $deletedFiles,
        ]);

        return Command::SUCCESS;
    }

    private function getDaysToKeep(): int
    {
        $setting = UserSetting::where('user_id', 1)->first();

        if ($setting && $setting->days_to_keep_lots) {
            return max(1, (int) $setting->days_to_keep_lots);
        }

        return self::DEFAULT_DAYS;
    }

    private function extractFileIds(Lot $lot): array
    {
        $fileIds = [];

        $lotAttachments = $lot->lot_attachments ?? [];
        foreach ($lotAttachments as $attachment) {
            if (isset($attachment['fileId'])) {
                $fileIds[] = $attachment['fileId'];
            }
        }

        $noticeAttachments = $lot->notice_attachments ?? [];
        foreach ($noticeAttachments as $attachment) {
            if (isset($attachment['fileId'])) {
                $fileIds[] = $attachment['fileId'];
            }
        }

        return array_unique($fileIds);
    }

    private function deleteFilesByFileId(string $storagePath, string $fileId): int
    {
        $deleted = 0;
        $pattern = $storagePath.'/'.preg_quote($fileId, '/').'.*';

        foreach (glob($pattern) as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function pluralize(int $n, string $one, string $few, string $many): string
    {
        $abs = abs($n) % 100;
        $lastDigit = $abs % 10;
        if ($abs > 10 && $abs < 20) {
            return $many;
        }
        if ($lastDigit > 1 && $lastDigit < 5) {
            return $few;
        }
        if ($lastDigit === 1) {
            return $one;
        }

        return $many;
    }
}

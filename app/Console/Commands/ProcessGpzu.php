<?php

namespace App\Console\Commands;

use App\GpzuProcessingStatus;
use App\GpzuService;
use Illuminate\Console\Command;

class ProcessGpzu extends Command
{
    protected $signature = 'gpzu:process {lotId} {fileId} {fileName}';

    protected $description = 'Process ГПЗУ file with OCR and extract data';

    public function __construct(
        private readonly GpzuService $gpzuService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lotId = $this->argument('lotId');
        $fileId = $this->argument('fileId');
        $fileName = $this->argument('fileName');

        $status = new GpzuProcessingStatus($lotId);

        if (! $status->acquireLock()) {
            $this->error("Processing already in progress for lot {$lotId}");

            return self::FAILURE;
        }

        try {
            $this->info("Starting ГПЗУ processing for lot {$lotId}...");

            $result = $this->gpzuService->processWithProgress($lotId, [
                'fileId' => $fileId,
                'fileName' => $fileName,
            ]);

            if ($result['success']) {
                $this->info("ГПЗУ processing completed successfully for lot {$lotId}");

                return self::SUCCESS;
            }

            $this->error("ГПЗУ processing failed: {$result['error']}");

            return self::FAILURE;
        } catch (\Exception $e) {
            $status->setError('Ошибка сервера: '.$e->getMessage());
            $this->error("Exception during processing: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $status->releaseLock();
        }
    }
}

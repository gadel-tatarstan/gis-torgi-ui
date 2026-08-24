<?php

namespace App\Http\Controllers;

use App\GpzuProcessingStatus;
use App\GpzuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GpzuController extends Controller
{
    public function __construct(
        private readonly GpzuService $gpzuService,
    ) {}

    /**
     * Get parsed ГПЗУ data for a lot.
     */
    public function getData(string $id): JsonResponse
    {
        if (! config('gpzu.enabled', true)) {
            return response()->json(['error' => 'Функция ГПЗУ отключена'], 404);
        }

        $data = $this->gpzuService->getDataForLot($id);

        return response()->json(['gpzu' => $data]);
    }

    /**
     * Get page numbers for drawing and appendix (triggers processing if needed).
     */
    public function pages(string $id): JsonResponse
    {
        if (! config('gpzu.enabled', true)) {
            return response()->json(['error' => 'Функция ГПЗУ отключена'], 404);
        }

        $data = $this->gpzuService->getDataForLot($id);

        if ($data) {
            return response()->json([
                'status' => 'done',
                'drawing_page' => $data->drawing_page,
                'appendix_page' => $data->appendix_page,
            ]);
        }

        // Check if processing is running
        $status = new GpzuProcessingStatus($id);
        $currentStatus = $status->getStatus();

        if ($status->isProcessing()) {
            return response()->json([
                'status' => 'processing',
                'progress' => $currentStatus,
            ]);
        }

        if ($currentStatus && $currentStatus['step'] === 'error') {
            return response()->json([
                'status' => 'error',
                'error' => $currentStatus['message'],
            ]);
        }

        return response()->json([
            'status' => 'idle',
        ]);
    }

    /**
     * Start ГПЗУ processing in background.
     */
    public function process(Request $request): JsonResponse
    {
        if (! config('gpzu.enabled', true)) {
            return response()->json(['error' => 'Функция ГПЗУ отключена'], 404);
        }

        $request->validate([
            'lot_id' => 'required|string',
            'file_id' => 'required|string',
            'file_name' => 'required|string',
        ]);

        $lotId = $request->input('lot_id');
        $fileId = $request->input('file_id');
        $fileName = $request->input('file_name');

        $existing = $this->gpzuService->getDataForLot($lotId);
        if ($existing) {
            return response()->json([
                'status' => 'done',
                'drawing_page' => $existing->drawing_page,
                'appendix_page' => $existing->appendix_page,
            ]);
        }

        $status = new GpzuProcessingStatus($lotId);

        if ($status->isProcessing()) {
            $currentStatus = $status->getStatus();

            return response()->json([
                'status' => 'processing',
                'progress' => $currentStatus,
            ]);
        }

        $this->launchBackgroundProcess($lotId, $fileId, $fileName);

        return response()->json([
            'status' => 'processing',
            'progress' => [
                'step' => 'start',
                'message' => 'Запуск обработки...',
            ],
        ]);
    }

    /**
     * Get current processing status for polling.
     */
    public function status(string $id): JsonResponse
    {
        if (! config('gpzu.enabled', true)) {
            return response()->json(['error' => 'Функция ГПЗУ отключена'], 404);
        }

        $status = new GpzuProcessingStatus($id);
        $currentStatus = $status->getStatus();

        if ($currentStatus && $currentStatus['step'] === 'done') {
            $data = $this->gpzuService->getDataForLot($id);

            return response()->json([
                'status' => 'done',
                'drawing_page' => $data?->drawing_page,
                'appendix_page' => $data?->appendix_page,
            ]);
        }

        if ($status->isProcessing()) {
            return response()->json([
                'status' => 'processing',
                'progress' => $currentStatus,
            ]);
        }

        if ($currentStatus && $currentStatus['step'] === 'error') {
            return response()->json([
                'status' => 'error',
                'error' => $currentStatus['message'],
            ]);
        }

        $data = $this->gpzuService->getDataForLot($id);
        if ($data) {
            return response()->json([
                'status' => 'done',
                'drawing_page' => $data->drawing_page,
                'appendix_page' => $data->appendix_page,
            ]);
        }

        return response()->json([
            'status' => 'idle',
        ]);
    }

    private function launchBackgroundProcess(string $lotId, string $fileId, string $fileName): void
    {
        $command = sprintf(
            'nohup php %s gpzu:process %s %s %s > /dev/null 2>&1 &',
            base_path('artisan'),
            escapeshellarg($lotId),
            escapeshellarg($fileId),
            escapeshellarg($fileName),
        );

        exec($command);
    }
}

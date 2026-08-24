<?php

namespace App\Http\Controllers;

use App\GpzuProcessingStatus;
use App\GpzuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Start ГПЗУ processing in background. Returns immediately.
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

        // Check if already processed
        $existing = $this->gpzuService->getDataForLot($lotId);
        if ($existing) {
            return response()->json([
                'status' => 'done',
                'gpzu' => $existing,
            ]);
        }

        $status = new GpzuProcessingStatus($lotId);

        // Check if already processing
        if ($status->isProcessing()) {
            $currentStatus = $status->getStatus();

            return response()->json([
                'status' => 'processing',
                'progress' => $currentStatus,
            ]);
        }

        // Launch background processing via Artisan command
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

        // Check if processing is done
        if ($currentStatus && $currentStatus['step'] === 'done') {
            $data = $this->gpzuService->getDataForLot($id);

            return response()->json([
                'status' => 'done',
                'gpzu' => $data,
            ]);
        }

        // Check if processing is running
        if ($status->isProcessing()) {
            return response()->json([
                'status' => 'processing',
                'progress' => $currentStatus,
            ]);
        }

        // Check if there was an error
        if ($currentStatus && $currentStatus['step'] === 'error') {
            return response()->json([
                'status' => 'error',
                'error' => $currentStatus['message'],
            ]);
        }

        // Check if data exists (was processed before)
        $data = $this->gpzuService->getDataForLot($id);
        if ($data) {
            return response()->json([
                'status' => 'done',
                'gpzu' => $data,
            ]);
        }

        return response()->json([
            'status' => 'idle',
        ]);
    }

    /**
     * Serve an extracted PDF page from ГПЗУ.
     */
    public function pdfPage(Request $request, string $id, int $page): StreamedResponse
    {
        if (! config('gpzu.enabled', true)) {
            abort(404);
        }

        $data = $this->gpzuService->getDataForLot($id);
        if (! $data) {
            abort(404, 'ГПЗУ не обработано');
        }

        $pdfPath = $this->gpzuService->getLocalPdfPath($data->file_id);
        if (! $pdfPath) {
            abort(404, 'PDF файл не найден');
        }

        $extractedPage = $this->gpzuService->extractPdfPage($pdfPath, $page);
        if (! $extractedPage || ! file_exists($extractedPage)) {
            abort(404, 'Страница не найдена');
        }

        return response()->stream(function () use ($extractedPage) {
            readfile($extractedPage);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Launch ГПЗУ processing in background.
     */
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

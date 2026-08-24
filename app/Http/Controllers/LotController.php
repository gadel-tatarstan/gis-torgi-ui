<?php

namespace App\Http\Controllers;

use App\DocumentService;
use App\Models\Etp;
use App\Models\Lot;
use App\Models\UserSetting;
use App\NspdService;
use App\TorgiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LotController extends Controller
{
    public function __construct(
        private readonly TorgiService $torgiService,
        private readonly NspdService $nspdService,
        private readonly DocumentService $documentService,
    ) {}

    public function index(Request $request): View
    {
        $etps = Etp::orderBy('order')->get();
        $yandexApiKey = config('services.yandex.maps_api_key', '');

        $gpzuEnabled = config('gpzu.enabled', true);

        return view('lots.index', compact('etps', 'yandexApiKey', 'gpzuEnabled'));
    }

    public function fetchLots(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);

        $filters = [
            'price_min_from' => $request->input('price_min_from', 1),
            'price_min_to' => $request->input('price_min_to', 2300000),
            'pub_from' => $request->input('pub_from', now()->format('Y-m-d')),
            'pub_to' => $request->input('pub_to', now()->format('Y-m-d')),
        ];

        $result = $this->torgiService->fetchLots($filters, $page);

        if (! empty($result['lots'])) {
            $this->torgiService->syncLots($result['lots']);
        }

        foreach ($result['lots'] as &$lot) {
            $existingLot = Lot::find($lot['id']);
            if ($existingLot) {
                $lot['is_viewed'] = $existingLot->is_viewed;
                $lot['is_not_interested'] = $existingLot->is_not_interested;
                $lot['on_board'] = $existingLot->on_board;
                $lot['comment'] = $existingLot->comment;
            }
        }

        $result['etps'] = Etp::orderBy('order')->pluck('short_name', 'code')->toArray();

        return response()->json($result);
    }

    public function showLotDetail(string $id): JsonResponse
    {
        $lot = Lot::find($id);

        if (! $lot) {
            $detail = $this->torgiService->getLotDetail($id);
            if (! $detail) {
                return response()->json(['error' => 'Лот не найден'], 404);
            }
            $parsed = $this->torgiService->parseLot($detail);
            $lot = Lot::create($parsed);
        }

        if (! $lot->is_viewed) {
            $lot->update(['is_viewed' => true]);
        }

        $detail = $this->torgiService->getLotDetail($id);

        $polygon = null;
        if ($lot->cadastral_number) {
            $polygon = $this->nspdService->getPolygonByCadastralNumber($lot->cadastral_number);
        }

        $etp = Etp::where('code', $lot->etp_code)->first();

        return response()->json([
            'lot' => $lot,
            'detail' => $detail,
            'polygon' => $polygon,
            'etp' => $etp,
        ]);
    }

    public function markNotInterested(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $lot = Lot::find($request->input('id'));
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $lot->update(['is_not_interested' => true]);

        return response()->json(['success' => true]);
    }

    public function markViewed(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $lot = Lot::find($request->input('id'));
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $lot->update(['is_viewed' => true]);

        return response()->json(['success' => true]);
    }

    public function fetchLotDetail(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $detail = $this->torgiService->getLotDetail($request->input('id'));

        if (! $detail) {
            return response()->json(['error' => 'Детали лота не найдены'], 404);
        }

        return response()->json($detail);
    }

    public function fetchPolygon(Request $request): JsonResponse
    {
        $request->validate(['cadastral_number' => 'required|string']);

        $polygon = $this->nspdService->getPolygonByCadastralNumber(
            $request->input('cadastral_number')
        );

        return response()->json($polygon);
    }

    public function settings(): View
    {
        $setting = UserSetting::firstOrCreate(['user_id' => auth()->id() ?? 1]);

        return view('lots.settings', compact('setting'));
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'yg_company_id' => 'nullable|string',
            'yg_api_token' => 'nullable|string',
            'yg_board_id' => 'nullable|string',
        ]);

        $setting = UserSetting::firstOrCreate(
            ['user_id' => auth()->id() ?? 1],
            $request->only(['yg_company_id', 'yg_api_token', 'yg_board_id'])
        );

        $setting->update($request->only(['yg_company_id', 'yg_api_token', 'yg_board_id']));

        return response()->json(['success' => true]);
    }

    public function addToYougile(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $setting = UserSetting::where('user_id', auth()->id() ?? 1)->first();

        if (! $setting || ! $setting->yg_api_token || ! $setting->yg_board_id) {
            return response()->json(['error' => 'Настройки YouGile не заполнены'], 400);
        }

        $lot = Lot::find($request->input('id'));
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $ygResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$setting->yg_api_token,
            'Content-Type' => 'application/json',
        ])->post("https://ru.yougile.com/api-v2/post/companies/{$setting->yg_company_id}/boards/{$setting->yg_board_id}/tasks", [
            'title' => "Участок: {$lot->cadastral_number}",
            'description' => $lot->lot_name."\n\nАдрес: ".($lot->estate_address ?? 'не указан')."\nЦена: ".number_format($lot->price_min, 2, ',', ' ')." руб.\nПлощадь: ".($lot->area ?? '—').' м²',
            'columnId' => $setting->yg_board_id,
        ]);

        if ($ygResponse->successful()) {
            $lot->update(['on_board' => true]);

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Ошибка создания карточки в YouGile'], 500);
    }

    /**
     * Preview a document — serves PDF directly (converted if needed).
     * Used in iframe for inline PDF viewing.
     */
    public function previewFile(Request $request): StreamedResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'file_name' => 'required|string',
        ]);

        $fileId = $request->input('file_id');
        $fileName = $request->input('file_name');

        $filePath = $this->documentService->getFileForPreview($fileId, $fileName);

        if (! $filePath || ! file_exists($filePath)) {
            abort(404, 'Файл не найден');
        }

        $mimeType = $this->documentService->getMimeType($filePath);

        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Download a file — proxied from torgi.gov.ru with caching.
     */
    public function downloadFile(Request $request): StreamedResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'file_name' => 'nullable|string',
        ]);

        $fileId = $request->input('file_id');
        $fileName = $request->input('file_name') ?? 'document';

        $filePath = $this->documentService->getFileForPreview($fileId, $fileName);

        if (! $filePath || ! file_exists($filePath)) {
            abort(404, 'Файл не найден');
        }

        $mimeType = $this->documentService->getMimeType($filePath);

        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Save or update a comment for a lot.
     */
    public function saveComment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:5000',
        ]);

        $lot = Lot::find($id);

        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $lot->update(['comment' => $request->input('comment')]);

        return response()->json(['success' => true, 'comment' => $lot->comment]);
    }
}

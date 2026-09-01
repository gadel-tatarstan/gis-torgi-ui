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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        return view('lots.index', compact('etps', 'yandexApiKey'));
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
                $lot['custom_address'] = $existingLot->custom_address;
                $lot['market_price'] = $existingLot->market_price;
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

    public function restoreInterested(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $lot = Lot::find($request->input('id'));
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $lot->update(['is_not_interested' => false]);

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
            'yg_column_id' => 'nullable|string',
            'days_to_keep_lots' => 'nullable|integer|min:1|max:365',
        ]);

        $data = $request->only(['yg_company_id', 'yg_api_token', 'yg_board_id', 'yg_column_id', 'days_to_keep_lots']);

        $setting = UserSetting::firstOrCreate(
            ['user_id' => auth()->id() ?? 1],
            $data
        );

        $setting->update($data);

        return response()->json(['success' => true]);
    }

    public function addToYougile(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string',
            'center_lat' => 'nullable|numeric',
            'center_lon' => 'nullable|numeric',
            'mercator_x' => 'nullable|numeric',
            'mercator_y' => 'nullable|numeric',
        ]);

        $setting = UserSetting::where('user_id', auth()->id() ?? 1)->first();

        if (! $setting || ! $setting->yg_api_token || ! $setting->yg_column_id) {
            return response()->json(['error' => 'Настройки YouGile не заполнены (токен и Column ID обязательны)'], 400);
        }

        $lot = Lot::find($request->input('id'));
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $torgiUrl = 'https://torgi.gov.ru/new/public/lots/lot/'.urlencode($lot->id);
        $address = $lot->custom_address ?: ($lot->estate_address ?? '');
        $title = ($lot->cadastral_number ?? 'Без номера').($address ? ' | '.$address : '').' | '.$torgiUrl;
        $idPrefix = 'GADEL_'.$lot->id;

        $deadlineTimestamp = null;
        if ($lot->bidd_end_time) {
            $deadlineTimestamp = $lot->bidd_end_time->timestamp * 1000;
        }
        $centerLat = $request->input('center_lat');
        $centerLon = $request->input('center_lon');
        $mercatorX = $request->input('mercator_x');
        $mercatorY = $request->input('mercator_y');

        $description = '';

        // Row 1: ЦИАН, ДомКлик
        $links = [];
        if ($centerLat !== null && $centerLon !== null) {
            $url = 'https://cian.ru/map/?center='.urlencode($centerLat.','.$centerLon).'&deal_type=sale&engine_version=2&object_type[0]=3&offer_type=suburban&zoom=15';
            $links[] = '<a href="'.$url.'" target="_blank">ЦИАН</a>';
            $swLat = $centerLat - 0.005;
            $swLon = $centerLon - 0.015;
            $neLat = $centerLat + 0.005;
            $neLon = $centerLon + 0.015;
            $url = 'https://domclick.ru/search/on-map?deal_type=sale&category=living&offer_type=lot&sw='.urlencode($swLat.','.$swLon).'&ne='.urlencode($neLat.','.$neLon);
            $links[] = '<a href="'.$url.'" target="_blank">ДомКлик</a>';
        }
        if ($links) {
            $description .= implode(' | ', $links);
        }

        // Row 2: Google Maps, Яндекс.Карты, НСПД
        $links2 = [];
        if ($centerLat !== null && $centerLon !== null) {
            $links2[] = '<a href="https://www.google.com/maps?q='.$centerLat.','.$centerLon.'" target="_blank">Google Maps</a>';
            $links2[] = '<a href="https://yandex.ru/maps/?ll='.$centerLon.'%2C'.$centerLat.'&z=17&l=sat%2Cskl" target="_blank">Яндекс.Карты</a>';
        }
        if ($mercatorX !== null && $mercatorY !== null) {
            $url = 'https://nspd.gov.ru/cadastral-price/search?zoom=18&coordinate_x='.$mercatorX.'&coordinate_y='.$mercatorY.'&baseLayerId=36344';
            $links2[] = '<a href="'.$url.'" target="_blank">НСПД</a>';
        }
        if ($links2) {
            $description .= '<br>'.implode(' | ', $links2);
        }

        if ($lot->comment) {
            $description .= '<br><br><strong>Комментарий:</strong><br>'.e($lot->comment);
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'columnId' => $setting->yg_column_id,
            'idTaskCommon' => $idPrefix,
            'idTaskProject' => $idPrefix,
            'idempotencyKey' => $idPrefix,
        ];

        if ($deadlineTimestamp) {
            $payload['deadline'] = [
                'deadline' => $deadlineTimestamp,
                'withTime' => true,
            ];
        }

        $stickers = [];

        // Площадка
        $etp = Etp::where('code', $lot->etp_code)->first();
        if ($etp && $etp->yg_sticker_id) {
            $stickers['0bf1e77e-694e-4135-a0df-0183155d4dbb'] = $etp->yg_sticker_id;
        }

        // Начальная цена
        $stickers['7c0874ef-adea-4df6-8088-cebf6070e032'] = self::formatPrice($lot->price_min).' ₽';

        // Шаг торгов
        if ($lot->price_step) {
            $stickers['d1023454-cde7-42d1-a095-ab350ff390f1'] = self::formatPrice($lot->price_step).' ₽';
        }

        // Задаток
        if ($lot->deposit) {
            $stickers['a0bbcf48-a930-498a-895c-4cdf868e6eb0'] = self::formatPrice($lot->deposit).' ₽';
        }

        // Площадь
        if ($lot->area) {
            $stickers['f8003c33-0e55-49a5-80ba-140e811b305d'] = number_format($lot->area, 0, '', ' ').' м²';
        }

        // ВРИ (жёсткое значение)
        $stickers['7a610416-adb7-45b1-bc6e-93245eadc664'] = '02d939539770';

        // Рыночная цена — только если заполнена
        if ($lot->market_price) {
            $stickers['80cf0261-750a-4256-9060-fefa746da57e'] = self::formatPrice($lot->market_price).' ₽';
        }

        // Коммуникации (жёсткое значение)
        $stickers['64839fb2-092a-42c7-b4c3-561a6cd10c3d'] = 'empty';

        // Дорога (жёсткое значение)
        $stickers['a09052a3-ed31-427f-ae3a-ad2503a76b55'] = 'empty';

        $payload['stickers'] = $stickers;

        $ygResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$setting->yg_api_token,
            'Content-Type' => 'application/json',
        ])->post('https://ru.yougile.com/api-v2/tasks', $payload);

        if ($ygResponse->successful()) {
            $ygData = $ygResponse->json();
            $lot->update([
                'on_board' => true,
                'yg_task_id' => $ygData['id'] ?? null,
            ]);

            return response()->json(['success' => true]);
        }

        Log::error('YouGile API error', [
            'lot_id' => $lot->id,
            'status' => $ygResponse->status(),
            'response' => $ygResponse->body(),
        ]);

        return response()->json([
            'error' => 'Ошибка создания карточки в YouGile',
            'details' => $ygResponse->json('message', $ygResponse->body()),
        ], 500);
    }

    public function saveMarketPrice(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'market_price' => 'nullable|numeric|min:0',
        ]);

        $lot = Lot::find($id);
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $value = $request->input('market_price');
        $lot->update(['market_price' => $value !== null && $value !== '' ? $value : null]);

        return response()->json(['success' => true]);
    }

    public function saveCustomAddress(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'custom_address' => 'nullable|string|max:500',
        ]);

        $lot = Lot::find($id);
        if (! $lot) {
            return response()->json(['error' => 'Лот не найден'], 404);
        }

        $value = $request->input('custom_address');
        $lot->update(['custom_address' => $value !== null && trim($value) !== '' ? trim($value) : null]);

        return response()->json(['success' => true]);
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

    private static function formatPrice(float $value): string
    {
        if ($value == (int) $value) {
            return number_format($value, 0, '', ' ');
        }

        return number_format($value, 2, '.', ' ');
    }
}

<?php

namespace App;

use App\Models\Lot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TorgiService
{
    private const BASE_URL = 'https://torgi.gov.ru/new/api/public/lotcards/search';

    private const LOT_DETAIL_URL = 'https://torgi.gov.ru/new/api/public/lotcards/';

    private const IMAGE_URL = 'https://torgi.gov.ru/new/image-preview/v1/';

    private const FILE_URL = 'https://torgi.gov.ru/new/file-store/v1/';

    public function fetchLots(array $filters = [], int $page = 1): array
    {
        $params = [
            'biddType' => 'ZK',
            'priceMinFrom' => $filters['price_min_from'] ?? 1,
            'priceMinTo' => $filters['price_min_to'] ?? 2300000,
            'lotStatus' => 'PUBLISHED,APPLICATIONS_SUBMISSION',
            'pubFrom' => $filters['pub_from'] ?? now()->format('Y-m-d'),
            'pubTo' => $filters['pub_to'] ?? now()->format('Y-m-d'),
            'catCode' => '301',
            'matchPhrase' => 'false',
            'typeTransaction' => 'sale',
            'byFirstVersion' => 'true',
            'withFacets' => 'true',
            'page' => $page,
            'size' => 10,
            'sort' => 'firstVersionPublicationDate,desc',
        ];

        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])->get(self::BASE_URL, $params);

            if ($response->failed()) {
                Log::error('Torgi API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'lots' => [],
                    'total_pages' => 0,
                    'total_elements' => 0,
                    'current_page' => 0,
                    'error' => 'Ошибка загрузки данных с сервера',
                ];
            }

            $data = $response->json();

            if (isset($data['empty']) && $data['empty'] === true) {
                return [
                    'lots' => [],
                    'total_pages' => 0,
                    'total_elements' => 0,
                    'current_page' => 0,
                    'empty' => true,
                ];
            }

            $lots = [];
            foreach ($data['content'] ?? [] as $lotData) {
                $lots[] = $this->parseLot($lotData);
            }

            return [
                'lots' => $lots,
                'total_pages' => $data['totalPages'] ?? 0,
                'total_elements' => $data['totalElements'] ?? 0,
                'current_page' => $data['number'] ?? 0,
                'page_size' => $data['size'] ?? 10,
            ];
        } catch (\Exception $e) {
            Log::error('Torgi API exception', ['message' => $e->getMessage()]);

            return [
                'lots' => [],
                'total_pages' => 0,
                'total_elements' => 0,
                'current_page' => 0,
                'error' => 'Ошибка подключения к серверу',
            ];
        }
    }

    public function getLotDetail(string $lotId): ?array
    {
        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])->get(self::LOT_DETAIL_URL.$lotId);

            if ($response->failed()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Torgi lot detail failed', ['id' => $lotId, 'message' => $e->getMessage()]);

            return null;
        }
    }

    public function parseLot(array $data): array
    {
        $characteristics = $data['characteristics'] ?? [];
        $permittedUse = null;
        $cadastralNumber = null;
        $area = null;
        $areaUnit = null;

        foreach ($characteristics as $char) {
            if ($char['code'] === 'PermittedUse' && isset($char['characteristicValue'][0]['code'])) {
                $permittedUse = $char['characteristicValue'][0]['code'];
            } elseif ($char['code'] === 'CadastralNumber') {
                $cadastralNumber = $char['characteristicValue'] ?? null;
            } elseif ($char['code'] === 'SquareZU') {
                $area = is_numeric($char['characteristicValue'] ?? null) ? (float) $char['characteristicValue'] : null;
                $areaUnit = $char['unit']['symbol'] ?? null;
            }
        }

        return [
            'id' => $data['id'],
            'notice_number' => $data['noticeNumber'] ?? '',
            'lot_number' => $data['lotNumber'] ?? 0,
            'bidd_form_code' => $data['biddForm']['code'] ?? null,
            'bidd_form_name' => $data['biddForm']['name'] ?? null,
            'lot_name' => $data['lotName'] ?? '',

            'price_min' => $data['priceMin'] ?? 0,
            'price_min_exact' => $data['priceMinExact'] ?? null,
            'lot_images' => $data['lotImages'] ?? [],
            'permitted_use' => $permittedUse,
            'cadastral_number' => $cadastralNumber,
            'area' => $area,
            'area_unit' => $areaUnit,
            'bidd_end_time' => $data['biddEndTime'] ?? null,
            'etp_code' => $data['etpCode'] ?? null,
            'etp_url' => $data['etpUrl'] ?? null,
            'estate_address' => $data['estateAddress'] ?? null,
            'create_date' => $data['createDate'] ?? null,
            'notice_first_version_publication_date' => $data['noticeFirstVersionPublicationDate'] ?? null,
            'lot_vat_name' => $data['lotVat']['name'] ?? null,
            'lot_status' => $data['lotStatus'] ?? null,
            'lot_attachments' => $data['lotAttachments'] ?? [],
            'notice_attachments' => $data['noticeAttachments'] ?? [],
            'characteristics_raw' => $data['characteristics'] ?? [],
            'attributes_raw' => $data['attributes'] ?? [],
        ];
    }

    public function syncLots(array $lotsFromApi): void
    {
        foreach ($lotsFromApi as $lotData) {
            $existing = Lot::find($lotData['id']);

            if ($existing) {
                if ($existing->create_date && isset($lotData['create_date'])) {
                    $existingDate = $existing->create_date instanceof Carbon
                        ? $existing->create_date->format('Y-m-d')
                        : substr($existing->create_date, 0, 10);
                    $newDate = Carbon::parse($lotData['create_date'])->format('Y-m-d');

                    if ($existingDate === $newDate) {
                        continue;
                    }
                }
            }

            $detail = $this->getLotDetail($lotData['id']);
            if ($detail) {
                $lotData = array_merge($lotData, $this->parseLotFromDetail($detail));
            }

            Lot::updateOrCreate(
                ['id' => $lotData['id']],
                $lotData
            );
        }
    }

    private function parseLotFromDetail(array $detail): array
    {
        return [
            'price_step' => $detail['priceStep'] ?? null,
            'deposit' => $detail['deposit'] ?? null,
            'etp_url' => $detail['etpUrl'] ?? null,
            'estate_address' => $detail['estateAddress'] ?? null,
            'auction_start_date' => $detail['auctionStartDate'] ?? null,
            'bidd_start_time' => $detail['biddStartTime'] ?? null,
            'bidd_end_time' => $detail['biddEndTime'] ?? null,
            'version_id' => $detail['versionId'] ?? null,
            'lat' => $detail['point']['lat'] ?? null,
            'lon' => $detail['point']['lon'] ?? null,
        ];
    }

    public function getImageUrl(string $imageId, bool $thumbnail = false): string
    {
        $url = self::IMAGE_URL.$imageId.'?disposition=inline';
        if ($thumbnail) {
            $url .= '&resize=600x600!';
        }

        return $url;
    }

    public function getFileUrl(string $fileId): string
    {
        return self::FILE_URL.$fileId;
    }
}

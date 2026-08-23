<?php

namespace App;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NspdService
{
    private const SEARCH_URL = 'https://nspd.gov.ru/api/geoportal/v2/search/cadastralPrice';

    public function getPolygonByCadastralNumber(string $cadastralNumber): ?array
    {
        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])->get(self::SEARCH_URL, [
                'query' => $cadastralNumber,
            ]);

            if ($response->failed()) {
                Log::warning('NSPD API request failed', [
                    'cadastral_number' => $cadastralNumber,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            $features = $data['data']['features'] ?? [];
            if (empty($features)) {
                return null;
            }

            $feature = $features[0];
            $rawCoordinates = $feature['geometry']['coordinates'][0] ?? [];
            $properties = $feature['properties'] ?? [];
            $options = $properties['options'] ?? [];

            if (empty($rawCoordinates)) {
                return null;
            }

            // Calculate center in EPSG:3857 before converting (needed for NSPD iframe URL)
            $mercatorCenter = $this->calculatePolygonCenter($rawCoordinates);

            // Convert EPSG:3857 (Web Mercator) → WGS84 (lat/lng)
            $coordinates = $this->convertFromEpsg3857ToWgs84($rawCoordinates);
            $center = $this->calculatePolygonCenter($coordinates);

            return [
                'coordinates' => $coordinates,
                'center_lat' => $center['lat'],
                'center_lon' => $center['lon'],
                'mercator_x' => $mercatorCenter['lon'],
                'mercator_y' => $mercatorCenter['lat'],
                'address' => $options['readable_address'] ?? null,
                'area' => $options['specified_area'] ?? null,
                'cad_cost' => $options['cost_value'] ?? null,
                'land_category' => $options['land_record_category_type'] ?? null,
                'permitted_use' => $options['permitted_use_established_by_document'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('NSPD polygon fetch failed', [
                'cadastral_number' => $cadastralNumber,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert array of EPSG:3857 coordinates to WGS84 (lat/lng).
     * Input:  [[easting, northing], ...]
     * Output: [[lng, lat], ...]
     */
    /**
     * Convert EPSG:3857 (Web Mercator) → WGS84 (lat/lng).
     * Uses WGS84 ellipsoid semi-major axis for precise conversion.
     *
     * @param  array  $coordinates  [[easting, northing], ...]
     * @return array [[lng, lat], ...]
     */
    private function convertFromEpsg3857ToWgs84(array $coordinates): array
    {
        $a = 6378137.0; // WGS84 semi-major axis in meters

        $result = [];
        foreach ($coordinates as $coord) {
            $lng = ($coord[0] / $a) * 180 / M_PI;
            $t = exp(-$coord[1] / $a);
            $lat = (M_PI / 2 - 2 * atan($t)) * 180 / M_PI;
            $result[] = [$lng, $lat];
        }

        return $result;
    }

    private function calculatePolygonCenter(array $coordinates): array
    {
        if (empty($coordinates)) {
            return ['lat' => 0, 'lon' => 0];
        }

        $sumLat = 0;
        $sumLon = 0;
        $count = count($coordinates);

        foreach ($coordinates as $coord) {
            $sumLon += $coord[0];
            $sumLat += $coord[1];
        }

        return [
            'lat' => $sumLat / $count,
            'lon' => $sumLon / $count,
        ];
    }
}

<?php

declare(strict_types=1);

// Development-only, resumable Nominatim acquisition. Never called by the application at runtime.
const DHAKA_BOUNDS = ['south' => 23.60, 'north' => 23.95, 'west' => 90.25, 'east' => 90.55];
const RATE_LIMIT_SECONDS = 1;

$root = dirname(__DIR__);
$taxonomy = json_decode(file_get_contents($root.'/database/data/dhaka-locations.json'), true, 512, JSON_THROW_ON_ERROR);
$outputPath = $root.'/database/data/dhaka-location-geo.json';
$reviewPath = $root.'/database/data/dhaka-location-geo-review.json';
$existing = is_file($outputPath) ? json_decode(file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR) : [];
$review = is_file($reviewPath) ? json_decode(file_get_contents($reviewPath), true, 512, JSON_THROW_ON_ERROR) : [];
$done = [];
foreach ([...$existing, ...$review] as $row) {
    $done[$row['area_slug'].'/'.$row['sub_area_slug']] = true;
}

function valid(array $candidate, string $area, string $sub): bool
{
    $lat = (float) ($candidate['lat'] ?? 0);
    $lon = (float) ($candidate['lon'] ?? 0);
    $name = strtolower(($candidate['display_name'] ?? '').' '.json_encode($candidate['address'] ?? []));

    return $lat >= DHAKA_BOUNDS['south'] && $lat <= DHAKA_BOUNDS['north'] && $lon >= DHAKA_BOUNDS['west'] && $lon <= DHAKA_BOUNDS['east']
        && str_contains($name, 'bangladesh') && (str_contains($name, strtolower($area)) || str_contains($name, strtolower($sub)));
}
function writeJson(string $path, array $data): void
{
    file_put_contents($path, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

foreach ($taxonomy as $area) {
    foreach ($area['sub_areas'] as $sub) {
        $key = $area['slug'].'/'.$sub['slug'];
        if (isset($done[$key])) {
            continue;
        }
        $accepted = null;
        $last = null;
        foreach (["{$sub['name']}, {$area['name']}, Dhaka, Bangladesh", "{$sub['name']}, Dhaka, Bangladesh", "{$area['name']} {$sub['name']}, Dhaka, Bangladesh"] as $query) {
            $url = 'https://nominatim.openstreetmap.org/search?'.http_build_query(['q' => $query, 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => 3]);
            $context = stream_context_create(['http' => ['header' => "User-Agent: AcheNakiGeoAcquisition/1.0 (development dataset; contact: geo@achenaki.example)\r\n", 'timeout' => 20]]);
            $body = @file_get_contents($url, false, $context);
            sleep(RATE_LIMIT_SECONDS);
            if ($body === false) {
                continue;
            } $results = json_decode($body, true) ?: [];
            $last = $results[0] ?? null;
            foreach ($results as $candidate) {
                if (valid($candidate, $area['name'], $sub['name'])) {
                    $accepted = $candidate;
                    break 2;
                }
            }
        }
        $row = ['area_slug' => $area['slug'], 'sub_area_slug' => $sub['slug'], 'source' => 'openstreetmap', 'verification' => $accepted ? 'VERIFIED' : 'UNRESOLVED'];
        if ($accepted) {
            $row += ['latitude' => (float) $accepted['lat'], 'longitude' => (float) $accepted['lon'], 'source_type' => $accepted['osm_type'] ?? null, 'source_id' => (string) ($accepted['osm_id'] ?? ''), 'display_name' => $accepted['display_name'] ?? null];
        }
        if ($accepted) {
            $existing[] = $row;
        } else {
            $review[] = $row;
        }
        writeJson($outputPath, $existing);
        writeJson($reviewPath, $review);
        echo "$key ".($accepted ? 'VERIFIED' : 'UNRESOLVED')."\n";
    }
}

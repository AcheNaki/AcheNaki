<?php

namespace Database\Seeders;

use App\Enums\CityCorporation;
use JsonException;
use UnexpectedValueException;

final class LocationDataset
{
    public function __construct(private readonly ?string $path = null) {}

    /**
     * @return list<array{name: string, slug: string, city_corporation: string, sub_areas: list<array{name: string, slug: string, bn_name?: string|null}>, bn_name?: string|null}>
     */
    public function load(): array
    {
        $path = $this->path ?? database_path('data/dhaka-locations.json');
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new UnexpectedValueException("Location dataset could not be read: {$path}");
        }

        try {
            $locations = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(
                "Location dataset contains malformed JSON: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($locations) || ! array_is_list($locations) || $locations === []) {
            throw new UnexpectedValueException('Location dataset must be a non-empty JSON array.');
        }

        $areaNames = [];
        $areaSlugs = [];

        foreach ($locations as $areaIndex => $area) {
            $pathLabel = "areas[{$areaIndex}]";
            $this->assertObject($area, $pathLabel, ['name', 'slug', 'city_corporation', 'sub_areas'], ['bn_name']);
            $this->assertName($area['name'], "{$pathLabel}.name");
            $this->assertSlug($area['slug'], "{$pathLabel}.slug");
            $this->assertOptionalName($area['bn_name'] ?? null, "{$pathLabel}.bn_name");

            if (! is_string($area['city_corporation']) || CityCorporation::tryFrom($area['city_corporation']) === null) {
                throw new UnexpectedValueException("{$pathLabel}.city_corporation must be DNCC or DSCC.");
            }

            $this->assertUnique($areaNames, $area['name'], "Duplicate major-area name: {$area['name']}");
            $this->assertUnique($areaSlugs, $area['slug'], "Duplicate major-area slug: {$area['slug']}");

            if (! is_array($area['sub_areas']) || ! array_is_list($area['sub_areas']) || $area['sub_areas'] === []) {
                throw new UnexpectedValueException("{$pathLabel}.sub_areas must be a non-empty array.");
            }

            $subAreaNames = [];
            $subAreaSlugs = [];

            foreach ($area['sub_areas'] as $subAreaIndex => $subArea) {
                $subPath = "{$pathLabel}.sub_areas[{$subAreaIndex}]";
                $this->assertObject($subArea, $subPath, ['name', 'slug'], ['bn_name']);
                $this->assertName($subArea['name'], "{$subPath}.name");
                $this->assertSlug($subArea['slug'], "{$subPath}.slug");
                $this->assertOptionalName($subArea['bn_name'] ?? null, "{$subPath}.bn_name");
                $this->assertUnique($subAreaNames, $subArea['name'], "Duplicate sub-area name in {$area['slug']}: {$subArea['name']}");
                $this->assertUnique($subAreaSlugs, $subArea['slug'], "Duplicate sub-area slug in {$area['slug']}: {$subArea['slug']}");
            }
        }

        return $locations;
    }

    /** @param list<string> $requiredKeys @param list<string> $optionalKeys */
    private function assertObject(mixed $value, string $path, array $requiredKeys, array $optionalKeys): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new UnexpectedValueException("{$path} must be a JSON object.");
        }

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $value)) {
                throw new UnexpectedValueException("{$path}.{$key} is required.");
            }
        }

        $unexpectedKeys = array_diff(array_keys($value), [...$requiredKeys, ...$optionalKeys]);

        if ($unexpectedKeys !== []) {
            throw new UnexpectedValueException("{$path} contains unsupported field: ".reset($unexpectedKeys));
        }
    }

    private function assertName(mixed $value, string $path): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 120) {
            throw new UnexpectedValueException("{$path} must be a non-blank string of at most 120 characters.");
        }
    }

    private function assertOptionalName(mixed $value, string $path): void
    {
        if ($value !== null) {
            $this->assertName($value, $path);
        }
    }

    private function assertSlug(mixed $value, string $path): void
    {
        if (! is_string($value) || strlen($value) > 120 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new UnexpectedValueException("{$path} must be a lowercase, hyphen-separated slug of at most 120 characters.");
        }
    }

    /** @param array<string, true> $seen */
    private function assertUnique(array &$seen, string $value, string $message): void
    {
        $key = mb_strtolower($value);

        if (isset($seen[$key])) {
            throw new UnexpectedValueException($message);
        }

        $seen[$key] = true;
    }
}

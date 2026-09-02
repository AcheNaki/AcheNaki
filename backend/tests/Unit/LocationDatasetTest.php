<?php

namespace Tests\Unit;

use Database\Seeders\LocationDataset;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use UnexpectedValueException;

class LocationDatasetTest extends TestCase
{
    public function test_canonical_dataset_is_valid_and_has_no_duplicate_keys(): void
    {
        $locations = (new LocationDataset)->load();

        $this->assertNotEmpty($locations);

        foreach ($locations as $location) {
            $this->assertContains($location['city_corporation'], ['DNCC', 'DSCC']);
            $this->assertNotEmpty($location['sub_areas']);
        }
    }

    #[DataProvider('invalidDatasetProvider')]
    public function test_invalid_datasets_fail_clearly(string $json, string $expectedMessage): void
    {
        $path = tempnam(sys_get_temp_dir(), 'achenaki-location-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $json);

        try {
            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage($expectedMessage);

            (new LocationDataset($path))->load();
        } finally {
            @unlink($path);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidDatasetProvider(): iterable
    {
        yield 'malformed JSON' => ['{', 'malformed JSON'];
        yield 'missing required field' => [
            '[{"name":"Area","slug":"area","city_corporation":"DNCC"}]',
            'sub_areas is required',
        ];
        yield 'invalid corporation' => [
            '[{"name":"Area","slug":"area","city_corporation":"OTHER","sub_areas":[{"name":"Locality","slug":"locality"}]}]',
            'must be DNCC or DSCC',
        ];
        yield 'duplicate area slug' => [
            '[{"name":"Area One","slug":"area","city_corporation":"DNCC","sub_areas":[{"name":"One","slug":"one"}]},{"name":"Area Two","slug":"area","city_corporation":"DSCC","sub_areas":[{"name":"Two","slug":"two"}]}]',
            'Duplicate major-area slug',
        ];
        yield 'duplicate area name' => [
            '[{"name":"Area","slug":"area-one","city_corporation":"DNCC","sub_areas":[{"name":"One","slug":"one"}]},{"name":"Area","slug":"area-two","city_corporation":"DSCC","sub_areas":[{"name":"Two","slug":"two"}]}]',
            'Duplicate major-area name',
        ];
        yield 'blank name' => [
            '[{"name":" ","slug":"area","city_corporation":"DNCC","sub_areas":[{"name":"Locality","slug":"locality"}]}]',
            'name must be a non-blank string',
        ];
        yield 'invalid slug' => [
            '[{"name":"Area","slug":"Not Stable","city_corporation":"DNCC","sub_areas":[{"name":"Locality","slug":"locality"}]}]',
            'must be a lowercase, hyphen-separated slug',
        ];
        yield 'empty child list' => [
            '[{"name":"Area","slug":"area","city_corporation":"DNCC","sub_areas":[]}]',
            'sub_areas must be a non-empty array',
        ];
        yield 'duplicate child name' => [
            '[{"name":"Area","slug":"area","city_corporation":"DNCC","sub_areas":[{"name":"Locality","slug":"one"},{"name":"Locality","slug":"two"}]}]',
            'Duplicate sub-area name',
        ];
        yield 'duplicate child slug' => [
            '[{"name":"Area","slug":"area","city_corporation":"DNCC","sub_areas":[{"name":"One","slug":"locality"},{"name":"Two","slug":"locality"}]}]',
            'Duplicate sub-area slug',
        ];
    }
}

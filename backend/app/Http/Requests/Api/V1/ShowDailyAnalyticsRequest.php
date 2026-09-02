<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class ShowDailyAnalyticsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $date = $this->input('date');
            if (! is_string($date) || $validator->errors()->has('date')) {
                return;
            }

            $timezone = (string) config('reporting.analytics.timezone');
            $requested = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
            if ($requested->greaterThan(CarbonImmutable::now($timezone)->startOfDay())) {
                $validator->errors()->add('date', 'The date must not be in the future.');
            }
        });
    }
}

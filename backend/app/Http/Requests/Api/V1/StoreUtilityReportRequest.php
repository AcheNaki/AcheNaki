<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ElectricityStatus;
use App\Enums\GasStatus;
use App\Enums\TimeBucket;
use App\Enums\UtilityType;
use App\Services\AnonymousReporterService;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUtilityReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $utilityInput = $this->input('utility_type');
        $utilityType = is_string($utilityInput) ? UtilityType::tryFrom($utilityInput) : null;
        $areaInput = $this->input('area_id');
        $areaId = is_int($areaInput) ? $areaInput : -1;
        $statusRule = match ($utilityType) {
            UtilityType::ELECTRICITY => Rule::enum(ElectricityStatus::class),
            UtilityType::GAS => Rule::enum(GasStatus::class),
            default => Rule::in([]),
        };

        return [
            'area_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('areas', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'sub_area_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('sub_areas', 'id')->where(fn (Builder $query) => $query
                    ->where('area_id', $areaId)
                    ->where('is_active', true)),
            ],
            'utility_type' => ['bail', 'required', Rule::enum(UtilityType::class)],
            'status' => ['bail', 'required', $statusRule],
            'time_bucket' => ['bail', 'required', Rule::enum(TimeBucket::class)],
            'can_cook' => [
                'nullable',
                'boolean',
                Rule::prohibitedIf($utilityType !== null && $utilityType !== UtilityType::GAS),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $header = $this->header((string) config('reporting.anonymous_header'));

            if (! app(AnonymousReporterService::class)->isValid(is_string($header) ? $header : null)) {
                $validator->errors()->add(
                    'anonymous_reporter',
                    'A valid anonymous reporter token is required.',
                );
            }
        });
    }

    /**
     * Only the pseudonymous representation leaves this request. PHP records scalar call
     * arguments in exception stack traces, so passing the raw token any deeper would put
     * it into `storage/logs` whenever a downstream write fails.
     */
    public function reporterTokenHash(): string
    {
        return app(AnonymousReporterService::class)
            ->hash((string) $this->header((string) config('reporting.anonymous_header')));
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ElectricityStatus;
use App\Enums\GasStatus;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListLiveStatusesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'utility' => ['sometimes', Rule::enum(UtilityType::class)],
            'status' => ['sometimes', Rule::enum(LiveStatus::class), Rule::notIn([LiveStatus::INSUFFICIENT_DATA->value])],
            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.config('reporting.aggregation.listing_max_limit'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $utilityInput = $this->input('utility');
            $utility = is_string($utilityInput) ? UtilityType::tryFrom($utilityInput) : null;
            $status = $this->input('status');
            if ($utility === null || ! is_string($status)) {
                return;
            }

            $allowed = $utility === UtilityType::ELECTRICITY
                ? [...array_column(ElectricityStatus::cases(), 'value'), LiveStatus::MIXED->value]
                : [...array_column(GasStatus::cases(), 'value'), LiveStatus::MIXED->value];

            if (! in_array($status, $allowed, true)) {
                $validator->errors()->add('status', 'The selected status is not valid for this utility.');
            }
        });
    }
}

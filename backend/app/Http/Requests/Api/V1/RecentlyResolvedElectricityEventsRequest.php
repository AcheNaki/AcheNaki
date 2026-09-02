<?php

namespace App\Http\Requests\Api\V1;

class RecentlyResolvedElectricityEventsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}

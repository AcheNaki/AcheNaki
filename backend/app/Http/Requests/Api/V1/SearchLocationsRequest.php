<?php

namespace App\Http\Requests\Api\V1;

class SearchLocationsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}

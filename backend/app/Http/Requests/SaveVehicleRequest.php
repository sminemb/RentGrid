<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveVehicleRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'display_label' => ['required', 'string', 'max:255'],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
        ];

        if ($this->route('vehicle')) {
            $originalField = ['present', 'nullable', 'string', 'max:255'];
            $rules += [
                'original_details' => ['required', 'array:display_label,make,model'],
                'original_details.display_label' => $originalField,
                'original_details.make' => $originalField,
                'original_details.model' => $originalField,
            ];
        }

        return $rules;
    }
}

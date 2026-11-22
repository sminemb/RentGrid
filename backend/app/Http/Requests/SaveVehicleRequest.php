<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveVehicleRequest extends FormRequest
{
    public function rules(): array
    {
        $uniqueLabel = Rule::unique('vehicles', 'display_label');
        if ($vehicle = $this->route('vehicle')) {
            $uniqueLabel->ignore($vehicle);
        }

        return [
            'display_label' => ['bail', 'required', 'string', 'max:255', $uniqueLabel],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
        ];
    }
}

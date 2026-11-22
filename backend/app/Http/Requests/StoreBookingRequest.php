<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quote_token' => ['required', 'string', 'max:4096'],
            'vehicle_id' => ['missing'],
            'pickup_date' => ['missing'],
            'return_date' => ['missing'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'phone' => ['required', 'string', 'max:40', 'regex:/\A\+?(?:[ ().-]*[0-9]){7,15}[ ().-]*\z/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a phone number with 7 to 15 digits.'];
    }
}

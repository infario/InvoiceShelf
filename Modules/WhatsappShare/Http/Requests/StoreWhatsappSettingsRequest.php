<?php

namespace Modules\WhatsappShare\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsappSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // International dialing code without '+' or leading zeros, e.g. 91, 1, 44.
            'default_country_code' => ['required', 'string', 'regex:/^[1-9][0-9]{0,3}$/'],
            'payment_link' => ['nullable', 'string', 'url', 'max:500'],
            'message_template' => ['required', 'string', 'max:1000'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

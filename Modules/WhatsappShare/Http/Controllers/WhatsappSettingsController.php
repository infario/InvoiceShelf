<?php

namespace Modules\WhatsappShare\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\WhatsappShare\Http\Requests\StoreWhatsappSettingsRequest;

/**
 * Per-company WhatsApp Share settings: the default country code used to
 * normalize customer phone numbers, and the message template sent with
 * each invoice. Values live in the host's company_settings table under
 * the whatsapp_share_* keys, so they survive module and app updates and
 * need no migration of their own.
 */
class WhatsappSettingsController extends Controller
{
    public const DEFAULT_COUNTRY_CODE = '91';

    public const DEFAULT_PAYMENT_LINK = 'https://infario.com/payments/';

    public const DEFAULT_MESSAGE_TEMPLATE = 'Hello {customer_name}, your invoice {invoice_number} of {amount_due} is due on {due_date}. Pay online: {payment_link} Thank you! - {company_name}';

    public function show(Request $request): JsonResponse
    {
        $company = $this->company($request);

        $this->authorize('manage company', $company);

        $paymentLink = CompanySetting::getSetting('whatsapp_share_payment_link', $company->id);

        return response()->json([
            'default_country_code' => CompanySetting::getSetting('whatsapp_share_default_country_code', $company->id) ?: self::DEFAULT_COUNTRY_CODE,
            'payment_link' => $paymentLink !== null && $paymentLink !== '' ? $paymentLink : self::DEFAULT_PAYMENT_LINK,
            'message_template' => CompanySetting::getSetting('whatsapp_share_message_template', $company->id) ?: self::DEFAULT_MESSAGE_TEMPLATE,
        ]);
    }

    public function store(StoreWhatsappSettingsRequest $request): JsonResponse
    {
        $company = $this->company($request);

        $this->authorize('manage company', $company);

        $validated = $request->validated();

        CompanySetting::setSettings([
            'whatsapp_share_default_country_code' => $validated['default_country_code'],
            'whatsapp_share_payment_link' => $validated['payment_link'] ?? '',
            'whatsapp_share_message_template' => $validated['message_template'],
        ], $company->id);

        return response()->json(['success' => true]);
    }

    private function company(Request $request): Company
    {
        return Company::findOrFail($request->header('company'));
    }
}

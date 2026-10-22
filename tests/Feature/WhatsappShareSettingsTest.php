<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Modules\WhatsappShare\Http\Controllers\WhatsappSettingsController;
use Modules\WhatsappShare\Http\Requests\StoreWhatsappSettingsRequest;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    Artisan::call('module:enable', ['module' => 'WhatsappShare']);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders([
        'company' => $this->companyId,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('store whatsapp settings using a form request', function () {
    $this->assertActionUsesFormRequest(
        WhatsappSettingsController::class,
        'store',
        StoreWhatsappSettingsRequest::class
    );
});

test('get whatsapp settings returns defaults when nothing is saved', function () {
    getJson('api/m/whatsapp-share/settings')
        ->assertOk()
        ->assertJson([
            'default_country_code' => WhatsappSettingsController::DEFAULT_COUNTRY_CODE,
            'message_template' => WhatsappSettingsController::DEFAULT_MESSAGE_TEMPLATE,
        ]);
});

test('store whatsapp settings persists per company', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '44',
        'message_template' => 'Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}.',
    ])->assertOk()
        ->assertJson(['success' => true]);

    expect(CompanySetting::getSetting('whatsapp_share_default_country_code', $this->companyId))->toBe('44')
        ->and(CompanySetting::getSetting('whatsapp_share_message_template', $this->companyId))
        ->toBe('Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}.');

    getJson('api/m/whatsapp-share/settings')
        ->assertOk()
        ->assertJson([
            'default_country_code' => '44',
            'message_template' => 'Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}.',
        ]);
});

test('store whatsapp settings rejects an invalid country code', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '+91',
        'message_template' => 'Hello {customer_name}',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['default_country_code']);
});

test('store whatsapp settings requires a message template', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '91',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['message_template']);
});

test('whatsapp settings are isolated between companies', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '1',
        'message_template' => 'Company A template',
    ])->assertOk();

    expect(CompanySetting::getSetting('whatsapp_share_default_country_code', $this->companyId))->toBe('1')
        ->and(CompanySetting::getSetting('whatsapp_share_default_country_code', 99999))->toBeNull();
});

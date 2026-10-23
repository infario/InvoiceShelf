<?php

use App\Models\CompanySetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Modules\WhatsappShare\Http\Controllers\WhatsappSettingsController;
use Modules\WhatsappShare\Http\Requests\StoreWhatsappSettingsRequest;
use Modules\WhatsappShare\Providers\WhatsappShareServiceProvider;

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
            'payment_link' => WhatsappSettingsController::DEFAULT_PAYMENT_LINK,
            'message_template' => WhatsappSettingsController::DEFAULT_MESSAGE_TEMPLATE,
        ]);
});

test('store whatsapp settings persists per company', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '44',
        'payment_link' => 'https://example.com/pay',
        'message_template' => 'Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}. Pay: {payment_link}',
    ])->assertOk()
        ->assertJson(['success' => true]);

    expect(CompanySetting::getSetting('whatsapp_share_default_country_code', $this->companyId))->toBe('44')
        ->and(CompanySetting::getSetting('whatsapp_share_payment_link', $this->companyId))->toBe('https://example.com/pay')
        ->and(CompanySetting::getSetting('whatsapp_share_message_template', $this->companyId))
        ->toBe('Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}. Pay: {payment_link}');

    getJson('api/m/whatsapp-share/settings')
        ->assertOk()
        ->assertJson([
            'default_country_code' => '44',
            'payment_link' => 'https://example.com/pay',
            'message_template' => 'Hi {customer_name}, invoice {invoice_number} of {amount_due} is due {due_date}. Pay: {payment_link}',
        ]);
});

test('store whatsapp settings rejects an invalid country code', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '+91',
        'message_template' => 'Hello {customer_name}',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['default_country_code']);
});

test('store whatsapp settings rejects an invalid payment link', function () {
    postJson('api/m/whatsapp-share/settings', [
        'default_country_code' => '91',
        'payment_link' => 'not-a-url',
        'message_template' => 'Hello {customer_name}',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['payment_link']);
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
        'payment_link' => 'https://a.example/pay',
        'message_template' => 'Company A template',
    ])->assertOk();

    expect(CompanySetting::getSetting('whatsapp_share_default_country_code', $this->companyId))->toBe('1')
        ->and(CompanySetting::getSetting('whatsapp_share_default_country_code', 99999))->toBeNull();
});

test('whatsapp share service provider appends a sidebar menu item', function () {
    // Tests boot before the DB exists, so AppServiceProvider skips addMenus().
    // Rebuild the host menu, then run the same append production uses.
    (new AppServiceProvider(app()))->addMenus();
    WhatsappShareServiceProvider::appendMainMenuItem();

    $item = collect(\Menu::get('main_menu')->items)
        ->first(fn ($i) => ($i->data['name'] ?? null) === 'WhatsApp Share');

    expect($item)->not->toBeNull()
        ->and($item->title)->toBe('whatsapp_share.nav_title')
        ->and($item->link->path['url'])->toBe('/admin/whatsapp-share')
        ->and($item->data['icon'])->toBe('ChatBubbleLeftRightIcon')
        ->and($item->data['ability'])->toBe('send-invoice')
        ->and($item->data['group'])->toBe(2);
});

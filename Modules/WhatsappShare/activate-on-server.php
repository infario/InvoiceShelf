<?php

/**
 * One-time WhatsappShare activator for shared hosting (no shell, no
 * phpMyAdmin required).
 *
 * This is the browser equivalent of:
 *   php artisan install:module WhatsappShare <version>
 *
 * It calls App\Space\ModuleInstaller::complete(), which migrates/seeds
 * (no-ops for this module), writes the module's entry in
 * storage/app/modules_statuses.json, inserts/updates the row in the
 * `modules` table, and clears the application caches — the three things
 * that make the module, its /admin/whatsapp-share page, and the
 * "Send on WhatsApp" invoice dropdown item appear.
 *
 * HOW TO USE
 * 1. Change $expectedKey below to a long random string (20+ characters).
 * 2. Upload THIS file into your install's public/ folder via FTP, e.g.
 *    public/whatsapp-activate-temp.php (pick any filename).
 * 3. Visit https://your-domain/whatsapp-activate-temp.php?key=YOUR-KEY
 *    You should see "OK" plus the modules-table row as JSON.
 * 4. DELETE the uploaded file immediately afterwards. While it exists,
 *    anyone who guesses the key can re-run it.
 *
 * The script refuses to run while the placeholder key is unchanged.
 */

$expectedKey = 'CHANGE-ME-to-a-long-random-string';

/*
 * =====================================================================
 *  Do not edit below this line.
 * =====================================================================
 */

if (str_starts_with($expectedKey, 'CHANGE-ME') || strlen($expectedKey) < 20) {
    http_response_code(500);
    exit('Refusing to run: set $expectedKey to a long random string (20+ characters) first.');
}

if (! hash_equals($expectedKey, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

// Locate the app root: walk up from this file until vendor/autoload.php
// and bootstrap/app.php are found (works from public/ or the app root).
$appRoot = null;
$dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    if (is_file($dir.'/vendor/autoload.php') && is_file($dir.'/bootstrap/app.php')) {
        $appRoot = $dir;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}

if ($appRoot === null) {
    http_response_code(500);
    exit('Failed: could not find the InvoiceShelf app root (vendor/autoload.php) above '.__DIR__);
}

require $appRoot.'/vendor/autoload.php';

$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \App\Space\ModuleInstaller::complete('WhatsappShare', '0.1.2');

    // Belt and braces: the statuses file is what loads the provider.
    $statusesFile = $appRoot.'/storage/app/modules_statuses.json';
    $statuses = is_file($statusesFile)
        ? (json_decode((string) file_get_contents($statusesFile), true) ?: [])
        : [];
    if (($statuses['WhatsappShare'] ?? null) !== true) {
        $statuses['WhatsappShare'] = true;
        file_put_contents($statusesFile, json_encode($statuses, JSON_PRETTY_PRINT).PHP_EOL);
    }

    // Shared hosting has no `php artisan optimize:clear`; this is it.
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    } catch (Throwable $cacheError) {
        echo 'Note: cache clear skipped ('.$cacheError->getMessage().")\n";
    }

    $row = \Illuminate\Support\Facades\DB::table('modules')
        ->where('name', 'WhatsappShare')
        ->first();

    if ($row === null || ! $row->installed || ! $row->enabled) {
        http_response_code(500);
        exit('Failed: modules-table row missing or not enabled: '.json_encode($row));
    }

    echo "OK — WhatsappShare is installed and enabled.\n";
    echo 'Row: '.json_encode($row)."\n";
    echo "Statuses: ".json_encode($statuses)."\n";
    echo "\nNow DELETE this file from public/, then hard-refresh (Ctrl+Shift+R)\n";
    echo "and check an invoice dropdown for 'Send on WhatsApp'.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed: '.$e->getMessage()."\n";
}

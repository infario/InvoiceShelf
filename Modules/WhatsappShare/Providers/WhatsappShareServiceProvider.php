<?php

namespace Modules\WhatsappShare\Providers;

use App\Services\Module\ModuleFacade;
use Illuminate\Support\ServiceProvider;

class WhatsappShareServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    protected $moduleName = 'WhatsappShare';

    /**
     * @var string
     */
    protected $moduleNameLower = 'whatsappshare';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerMainMenuItem();

        // Serve the module's built frontend bundle through the host shell
        // (resources/views/app.blade.php iterates ModuleFacade::allScripts()).
        // The dist files are built by the module's own Vite config and are
        // committed inside the module so an install never needs a build step.
        $script = module_path($this->moduleName, 'dist/whatsapp-share.es.js');
        if (file_exists($script)) {
            ModuleFacade::script('whatsapp-share', $script);
        }

        $style = module_path($this->moduleName, 'dist/style.css');
        if (file_exists($style)) {
            ModuleFacade::style('whatsapp-share', $style);
        }
    }

    /**
     * Append a sidebar entry after the host has built main_menu from
     * config/invoiceshelf.php. Visible to users who can send invoices.
     */
    protected function registerMainMenuItem(): void
    {
        $this->app->booted(function () {
            static::appendMainMenuItem();
        });
    }

    /**
     * Idempotent append used by the booted hook and by tests that rebuild
     * the menu after the DB exists (tests skip addMenus during boot).
     */
    public static function appendMainMenuItem(): void
    {
        $menu = \Menu::get('main_menu');

        if (! $menu) {
            return;
        }

        $already = collect($menu->items)->contains(
            fn ($item) => ($item->data['name'] ?? null) === 'WhatsApp Share'
        );

        if ($already) {
            return;
        }

        $menu->add('whatsapp_share.nav_title', '/admin/whatsapp-share')
            ->data('icon', 'ChatBubbleLeftRightIcon')
            ->data('name', 'WhatsApp Share')
            ->data('owner_only', false)
            ->data('ability', 'send-invoice')
            ->data('model', '')
            ->data('group', 2);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}

<?php

namespace App\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers app/Modules/* : registers each module's policies, event listeners and console commands.
 * Routes are loaded from routes/api.php (see there).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (glob(app_path('Modules/*/Policies/*.php')) ?: [] as $file) {
            $module = basename(dirname($file, 2));
            $class = 'App\\Modules\\'.$module.'\\Policies\\'.basename($file, '.php');
            $model = 'App\\Models\\'.str_replace('Policy', '', basename($file, '.php'));
            if (class_exists($class) && class_exists($model)) {
                Gate::policy($model, $class);
            }
        }

        // module event listeners: app/Modules/*/events.php
        foreach (glob(app_path('Modules/*/events.php')) ?: [] as $file) {
            require $file;
        }

        if ($this->app->runningInConsole()) {
            $commands = [];
            foreach (glob(app_path('Modules/*/Console/*.php')) ?: [] as $file) {
                $module = basename(dirname($file, 2));
                $commands[] = 'App\\Modules\\'.$module.'\\Console\\'.basename($file, '.php');
            }
            $this->commands($commands);
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;

class DatabaseServiceProvider extends ServiceProvider
{

    /**
     * config database connection according to url path
     */
    public function boot(): void {
        $isPassdropitRequest = $this->app->request->is('api/'.config('app.api-version').'/passdropit/*');
        Config::set('database.connections.mysql',
            $isPassdropitRequest
                ? Config::get('database.connections.passdropit')
                : Config::get('database.connections.notions11'));
    }
}

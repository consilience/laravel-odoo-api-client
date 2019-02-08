<?php

namespace Consilience\OdooApi;

/**
 *
 */

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Consilience\OpwallReports\Models\ReportLine;
use Consilience\OpwallReports\Observers\ReportLineObserver;

class OdooServiceProvider extends BaseServiceProvider
{
    protected $defer = false;

    // Where this service sits in the container.
    // We may not actually need this service, since we are only implementing
    // route providers, user providers, models, controllers and views.

    const PROVIDES = 'odoo-api';

    public function provides()
    {
        return [static::PROVIDES];
    }

    public function boot()
    {
        $configFilename = static::PROVIDES . '.php';

        $this->publishes([
            __DIR__ . '/../config/' . $configFilename => config_path($configFilename),
        ]);
    }

    public function register()
    {
        $configFilename = static::PROVIDES . '.php';

        $this->mergeConfigFrom(
            __DIR__ . '/../config/' . $configFilename, static::PROVIDES
        );

        $this->app->singleton(static::PROVIDES, function ($app) {
            $odooService = new OdooService();
/*
            $odooApi->setClientParams(
                config('opwall-reports.openerp.url'),
                config('opwall-reports.openerp.port')
            );

            $odooApi->getConnection(
                config('opwall-reports.openerp.database'),
                config('opwall-reports.openerp.username'),
                config('opwall-reports.openerp.password')
            );
*/
            return $odooService;
        });
    }
}

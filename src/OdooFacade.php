<?php

namespace Consilience\OdooApi;

/**
 *
 */

use \Illuminate\Support\Facades\Facade as BaseFacade;

class OdooFacade extends BaseFacade {
    protected static function getFacadeAccessor()
    {
        return OdooServiceProvider::PROVIDES;
    }
}

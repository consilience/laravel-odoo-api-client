<?php

namespace Consilience\OdooApi;

/**
 *
 */

use JsonSerializable;

interface ModelInterface extends JsonSerializable
{
    public function get($key, $default = null);
}

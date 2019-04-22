<?php

namespace Consilience\OdooApi;

/**
 *
 */

use JsonSerializable;
use ArrayAccess;

interface ModelInterface extends JsonSerializable, ArrayAccess
{
    /**
     * Get a model instance data item, using "dot" notation.
     *
     * @param string $key example 'parent_ids.2'
     * @param mixed $defuault
     * @returns mixed
     */
    public function get(string $key, $default = null);
}

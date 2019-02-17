<?php

namespace Consilience\OdooApi;

/**
 *
 */

use JsonSerializable;

interface ModelInterface extends JsonSerializable
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

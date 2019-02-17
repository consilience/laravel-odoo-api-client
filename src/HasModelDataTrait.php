<?php

namespace Consilience\OdooApi;

/**
 * Methods to initialise, and get data items.
 */

trait HasModelDataTrait
{
    /**
     * Data structure as returned by the API and converted to
     * a native PHP array.
     */
    protected $data = [];

    /**
     * Instantiate with the array data from the ERP model read.
     */
    public function __construct(array $data = [])
    {
        // Store away the source data.
        $this->setData($data);
    }

    protected function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * Get a data field using a "dot notation" path.
     */
    public function get($key, $default = null)
    {
        // Since we are running under laravel, use laravel's helper.

        return data_get($this->data, $key, $default);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
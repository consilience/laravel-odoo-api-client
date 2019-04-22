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
     *
     * @inherit
     */
    public function get(string $key, $default = null)
    {
        // Since we are running under laravel, use laravel's helper.

        return data_get($this->data, $key, $default);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Supports ArrayAccess
     */
    public function offsetExists($offset): bool
    {
        return $this->get($offset) !== null;
    }

    /**
     * Supports ArrayAccess
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Supports ArrayAccess
     */
    public function offsetSet($offset, $value): void
    {
        data_set($this->data, $offset, $value);
    }

    /**
     * Supports ArrayAccess
     */
    public function offsetUnset($offset): void
    {
        $this->offsetSet($offset, null);
    }
}

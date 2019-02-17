<?php

namespace Consilience\OdooApi;

/**
 *
 */

class OdooService
{
    /**
     * All clients created, indexed by configuration name.
     */
    protected $clients = [];

    /**
     * Get a client singleton
     *
     * @param string $configName
     */
    public function getClient(string $configName = null)
    {
        if (array_key_exists($configName, $this->clients)) {
            return $this->clients[$configName];
        }

        // Get the connection data set.

        if ($configName === null) {
            $configName = config('odoo-api.default');
        }

        $config = config('odoo-api.connections.' . $configName);

        $client = $this->clients[$configName] = new OdooClient($config);

        // Start with the top level mappings.

        $modelMap = config('odoo-api.model_map', []);

        $client->addModelMap($modelMap);

        // Override with any mappings specific for the connection.

        $modelMap = config('odoo-api.connections.' . $configName . '.model_map', []);

        $client->addModelMap($modelMap);

        return $client;
    }
}

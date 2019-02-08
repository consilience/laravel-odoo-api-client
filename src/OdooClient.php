<?php

namespace Consilience\OdooApi;

/**
 * Not a singleton; we could have multiple clients to multiple Odoo instances.
 */

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;

use Exception;

class OdooClient
{
    /**
     * The datatypes accepted by the `value` method.
     */
    const TYPE_INT      = 'int';
    const TYPE_STRING   = 'string';
    const TYPE_STRUCT   = 'struct';
    const TYPE_ARRAY    = 'array';
    const TYPE_BOOLEAN  = 'boolean';
    const TYPE_DOUBLE   = 'double';
    const TYPE_NULL     = 'null';

    /**
     *
     */
    protected $endpointTemplate = '{uri}/xmlrpc/{type}';

    /**
     * PhpXmlRpc\Client indexed by client type.
     */
    protected $xmlRpcClients = [];

    /**
     * int the user ID used to access the endpoints.
     */
    protected $userId;

    /**
     * Config data.
     */
    protected $config = [];

    /**
     * Config data broken out into credentials.
     */
    protected $url;
    protected $database;
    protected $username;
    protected $password;

    /**
     * @param array $config the connection configuration details
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->url = $config['url'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    /**
     * Get an XML ROC client singleton of a particular type.
     */
    public function getXmlRpcClient(string $type)
    {
        if (array_key_exists($type, $this->xmlRpcClients)) {
            return $this->xmlRpcClients[$type];
        }

        $type = strtolower($type);

        $endpoint = str_replace(
            ['{uri}', '{type}'],
            [$this->url, $type],
            $this->endpointTemplate
        );

        $xmlRpcClient = new Client($endpoint);

        $this->xmlRpcClients[$type] = $xmlRpcClient;

        return $xmlRpcClient;
    }

    /**
     * Get the user ID, fetching from the Odoo server if necessary.
     */
    public function getUserId()
    {
        if ($this->userId !== null) {
            return $this->userId;
        }

        // Fetch the user ID from the server.

        $xmlRpcClient = $this->getXmlRpcClient('common');

        // Build login parameters array.

        $params = [
            $this->stringValue($this->database),
            $this->stringValue($this->username),
            $this->stringValue($this->password),
        ];

        // Build a Request object.

        $msg = new Request('login', new Value($params, static::TYPE_ARRAY));

        // Send the request.

        try {
            $response = $xmlRpcClient->send($msg);
        } catch (Exception $e) {
            // Some connection problem.

            throw new Exception(sprintf(
                'Cannot connect to Odoo database "%s"',
                $this->config['database']
            ), null, $e);
        }

        // Grab the User ID.

        $userId = $response->value()->me['int'];

        if ($userId > 0) {
            return $userId;
        }

        throw new Exception(sprintf(
            'Cannot find Odoo user ID for username "%s"',
            $this->config['username']
        ));
    }

    /**
     *
     */
    public function value($data, $type)
    {
        return new Value($data, $type);
    }

    public function stringValue(string $data)
    {
        return $this->value($data, static::TYPE_STRING);
    }

    public function arrayValue(array $data)
    {
        return $this->value($data, static::TYPE_ARRAY);
    }

    public function intValue(int $data)
    {
        return $this->value($data, static::TYPE_INT);
    }

    public function structValue(array $data)
    {
        return $this->value($data, static::TYPE_STRUCT);
    }

    public function booleanValue(bool $data)
    {
        return $this->value($data, static::TYPE_BOOLEAN);
    }

    public function doubleValue(float $data)
    {
        return $this->value($data, static::TYPE_DOUBLE);
    }

    public function nullValue()
    {
        return $this->value(null, static::TYPE_NULL);
    }

    /**
     * Example:
     * OdooApi::getClient()->search('res.partner', $criteria, 0, 10)
     */
    public function search(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = 100,
        $order = ''
    ) {
        $msg = new Request('execute');

        $msg->addParam($this->stringValue($this->database));
        $msg->addParam($this->intValue($this->getUserId()));
        $msg->addParam($this->stringValue($this->password));

        $msg->addParam($this->stringValue($modelName));
        $msg->addParam($this->stringValue('search'));
        $msg->addParam($this->objectifyArray($criteria));

        $msg->addParam($this->intValue($offset)); // offset
        $msg->addParam($this->intValue($limit));  // limit
        $msg->addParam($this->stringValue($order)); // order, CSV list

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response;
    }

    /**
     * Example:
     * OdooApi::getClient()->search_count('res.partner', $criteria)
     */
    public function searchCount(
        string $modelName,
        array $criteria = []
    ) {
        $msg = new Request('execute');

        $msg->addParam($this->stringValue($this->database));
        $msg->addParam($this->intValue($this->getUserId()));
        $msg->addParam($this->stringValue($this->password));

        $msg->addParam($this->stringValue($modelName));
        $msg->addParam($this->stringValue('search_count'));
        $msg->addParam($this->objectifyArray($criteria));

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response->value()->me['int'];
    }

    /**
     * Example:
     * OdooApi::getClient()->search('res.partner', $criteria, 0, 10)
     */
    public function searchRead(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = 100,
        $order = ''
    ) {
        $msg = new Request('execute');

        $msg->addParam($this->stringValue($this->database));
        $msg->addParam($this->intValue($this->getUserId()));
        $msg->addParam($this->stringValue($this->password));

        $msg->addParam($this->stringValue($modelName));
        $msg->addParam($this->stringValue('search_read'));
        $msg->addParam($this->objectifyArray($criteria));

        // To be fixed when we have Odoo 8 available.

        //$msg->addParam($this->stringValue('id'));

        //$msg->addParam($this->stringValue($offset)); // offset
        //$msg->addParam($this->intValue($limit));  // limit
        //$msg->addParam($this->stringValue($order)); // order, CSV list
        //$msg->addParam($this->structValue(['fields' => $this->arrayValue(['id', 'name'])]));

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response;
    }

    /**
     * $criteria is an array of IDs.
     */
    public function read(
        string $modelName,
        array $criteria = []
    ) {
        $msg = new Request('execute');

        $msg->addParam($this->stringValue($this->database));
        $msg->addParam($this->intValue($this->getUserId()));
        $msg->addParam($this->stringValue($this->password));

        $msg->addParam($this->stringValue($modelName));
        $msg->addParam($this->stringValue('read'));
        $msg->addParam($this->objectifyArray($criteria));

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response;
    }

    //
    // TODO: create write unlink
    //

    /**
     * Walk through the criteria array and convert scalar values to
     * XML-RPC objects, and nested arrays to array and struct objects.
     */
    public function objectifyArray($item)
    {
        // If a scalar, then map to the appropriate object.

        if (! is_array($item)) {
            if (gettype($item) === 'integer') {
                return $this->intValue($item);
            } elseif (gettype($item) === 'string') {
                return $this->stringValue($item);
            } elseif (gettype($item) === 'double') {
                return $this->doubleValue($item);
            } elseif (gettype($item) === 'boolean') {
                return $this->booleanValue($item);
            } elseif ($item === null) {
                return $this->nullValue();
            } elseif ($item instanceof Value) {
                // Already mapped externaly to a Value object.
                return $item;
            } else {
                // No idea what it is, so don't know how to handle it.
                throw new Exception(sprintf(
                    'Unrecognised data type %s',
                    gettype($item)
                ));
            }
        }

        // If an array, then deal with the children first.

        foreach ($item as $key => $element) {
            $item[$key] = $this->objectifyArray($element);
        }

        // Map to an array or a struct, depending on whether a numeric
        // array or associative array.

        if ($item === [] || array_keys($item) === range(0, count($item) - 1)) {
            return $this->arrayValue($item);
        } else {
            return $this->structValue($item);
        }
    }
}

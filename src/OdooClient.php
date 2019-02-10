<?php

namespace Consilience\OdooApi;

/**
 * Not a singleton; we could have multiple clients to multiple Odoo instances.
 */

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Response;

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

    const DEFAULT_LIMIT = 100;

    /**
     * Later versions of the API include a version number e.g. /xmlrpc/2/
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
     * The version of the server, fetched when logging in.
     */
    protected $serverVersion;

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
     * Get an XML RRC client singleton of a particular type.
     *
     * @param string $type One of: 'common', 'object', 'db'
     * @return Client 
     */
    public function getXmlRpcClient(string $type)
    {
        $type = strtolower($type);

        if (array_key_exists($type, $this->xmlRpcClients)) {
            return $this->xmlRpcClients[$type];
        }

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

        $this->userId = $this->valueToNative($response->value());

        // Get the server version for capabilities.

        $version = $this->version();

        $this->serverVersion = $version['server_version'] ?? '';

        if ($this->userId > 0) {
            return $this->userId;
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
     *
     * @param string $modelName example res.partner
     * @param array $criteria nested array of search criteria (Polish notation logic)
     * @param int $offset
     * @param int $limit
     * @param string $order comma-separated list of fields
     * @return Response
     */
    public function search(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $order = ''
    ) {
        $msg = $this->getBaseObjectRequest($modelName, 'search');

        $msg->addParam($this->nativeToValue($criteria));

        $msg->addParam($this->intValue($offset));
        $msg->addParam($this->intValue($limit));
        $msg->addParam($this->stringValue($order));

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response;
    }

    /**
     * Same as search() but returns a native array.
     *
     * @return array
     */
    public function searchArray(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $order = ''
    ) {
        $response = $this->search(
            $modelName,
            $criteria,
            $offset,
            $limit,
            $order
        );

        if ($response->value() instanceof Value) {
            return $this->valueToNative($response->value());
        }

        // An error in the criteria or model provided.

        throw new Exception(sprintf(
            'Failed to search model %s; response was "%s"',
            $modelName,
            $response->value()
        ));
    }

    /**
     * Example:
     * OdooApi::getClient()->searchCount('res.partner', $criteria)
     *
     * @return integer
     */
    public function searchCount(
        string $modelName,
        array $criteria = []
    ) {
        $msg = $this->getBaseObjectRequest($modelName, 'search_count');

        $msg->addParam($this->nativeToValue($criteria));

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $this->valueToNative($response->value());
    }

    /**
     * Example:
     * OdooApi::getClient()->search('res.partner', $criteria, 0, 10)
     */
    public function searchRead(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $order = ''
    ) {
        if (version_compare('8.0', $this->serverVersion) === 1) {
            // Less than Odoo 8.0, so search_read is not supported.
            // However, we will emulate it.

            $ids = $this->searchArray(
                $modelName,
                $criteria,
                $offset,
                $limit,
                $order
            );

            return $this->read($modelName, $ids);
        } else {
            $msg = $this->getBaseObjectRequest($modelName, 'search_read');

            $msg->addParam($this->nativeToValue($criteria));

            $msg->addParam($this->intValue($offset));
            $msg->addParam($this->intValue($limit));
            $msg->addParam($this->stringValue($order));

            $response = $this->getXmlRpcClient('object')->send($msg);
        }

        return $response;
    }

    /**
     * Same as searchRead but returning a native PHP array.
     */
    public function searchReadArray(
        string $modelName,
        array $criteria = [],
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $order = ''
    ) {
        $response = $this->searchRead(
            $modelName,
            $criteria,
            $offset,
            $limit,
            $order
        );

        return $this->valueToNative($response->value());
    }

    /**
     * @param string $modelName example res.partner
     * @param array $instanceIds list of model instance IDs to read and return
     * @param array $options varies with API versions see documentation
     * @return Response
     */
    public function read(
        string $modelName,
        array $instanceIds = [],
        array $options = []
    ) {
        $msg = $this->getBaseObjectRequest($modelName, 'read');

        $msg->addParam($this->nativeToValue($instanceIds));

        if (! empty($options)) {
            $msg->addParam($this->nativeToValue($options));
        }

        $response = $this->getXmlRpcClient('object')->send($msg);

        return $response;
    }

    /**
     * Same as read() but returns a native array.
     */
    public function readArray(
        string $modelName,
        array $instanceIds = [],
        array $options = []
    ) {
        $response = $this->read(
            $modelName,
            $instanceIds,
            $options
        );

        if ($response->value() instanceof Value) {
            return $this->valueToNative($response->value());
        }

        // An error in the instanceIds or model provided.

        throw new Exception(sprintf(
            'Failed to read model %s; response was "%s"',
            $modelName,
            $response->value()
        ));
    }

    /**
     * Get the server version information.
     *
     * @return array
     */
    public function version()
    {
        $msg = new Request('version');

        $response = $this->getXmlRpcClient('common')->send($msg);

        return $this->valueToNative($response->value());
    }

    //
    // TODO: actions to implement = create write unlink
    // Also: fields_get, version
    //

    /**
     * Get the ERP internal resource ID for a given external ID.
     * This in thoery kind of belongs in a wrapper to the client,
     * but is used so often in data syncs that it makes sense having
     * it here.
     *
     * @param string $externalId either "name" or "module.name"
     * @param string $module optional, but recommended
     * @return int|null
     */
    public function getResourceId(string $externalId, string $model = null)
    {
        $criteria = [];

        if ($model !== null) {
            $criteria[] = ['model', '=', 'res.partner'];
        }

        if (strpos($externalId, '.') !== false) {
            list ($module, $name) = explode('.', $externalId, 2);

            $criteria[] = ['module', '=', $module];
        } else {
            $name = $externalId;
        }

        $criteria[] = ['name', '=', $name];

        $irModelDataIds = $this->searchArray('ir.model.data', $criteria);
        $irModelDataId = collect($irModelDataIds)->first();

        if ($irModelDataId === null) {
            // No matches found, so give up now.
            return;
        }

        // Now read the full record to get the resource ID.

        $irModelDataArray = $this->readArray(
            'ir.model.data',
            [$irModelDataId]
        );
        $irModelData = collect($irModelDataArray)->first();

        if ($irModelData === null) {
            // We could not find the record.
            // (We really should have, since we just looked it up)
            return;
        }

        // Return the resource ID.

        return $irModelData['res_id'];
    }

    /**
     * Return a message with the base parameters for any object call.
     * Identified the login credentials, model and action.
     *
     * @param string|null $modelName
     * @param string|null $action will be used only the $modelName is provided
     * @return Request
     */
    public function getBaseObjectRequest(
        string $modelName = null,
        string $action = null
    ) {
        $msg = new Request('execute');

        $msg->addParam($this->stringValue($this->database));
        $msg->addParam($this->intValue($this->getUserId()));
        $msg->addParam($this->stringValue($this->password));

        if ($modelName !== null) {
            $msg->addParam($this->stringValue($modelName));

            if ($action !== null) {
                $msg->addParam($this->stringValue($action));
            }
        }

        return $msg;
    }

    /**
     * Walk through the criteria array and convert scalar values to
     * XML-RPC objects, and nested arrays to array and struct objects.
     */
    public function nativeToValue($item)
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
            $item[$key] = $this->nativeToValue($element);
        }

        // Map to an array or a struct, depending on whether a numeric
        // keyed array or an associative array is to be encoded.

        if ($item === [] || array_keys($item) === range(0, count($item) - 1)) {
            return $this->arrayValue($item);
        } else {
            return $this->structValue($item);
        }
    }

    /**
     * Convert a Value object into native PHP types.
     * Basically the reverse of nativeToValue().
     *
     * @param Value the object to convert, which may contain nested objects
     * @returns mixed a null, an array, a scalar, and may be nested
     */
    public function valueToNative(Value $value)
    {
        switch ($value->kindOf()) {
            case 'array':
                $result = [];
                foreach ($value->getIterator() as $element) {
                    $result[] = $this->valueToNative($element);
                }
                break;
            case 'struct':
                $result = [];
                foreach ($value->getIterator() as $key => $element) {
                    $result[$key] = $this->valueToNative($element);
                }
                break;
            case 'scalar':
                return $value->scalarval();
                break;
            default:
                throw new Exception(sprintf(
                    'Unexpected data type %s',
                    $value->kindOf()
                ));
        }

        return $result;
    }
}

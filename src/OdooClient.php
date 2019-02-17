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
     * The action to perform when updating a one-to-many relation.
     * Note: a many-to-many relation is treated like a one-to-many
     * relation from each side.
     * - Actions 0 to 2 manage CRUD operations on the models being
     *   linked to.
     * - Actions 3 to 6 manage just the relationship between existing
     *   models.
     */
    // Adds a new record created from the provided value dict.
    // (0, _, values)
    const RELATION_CREATE = 0;
    //
    // Updates an existing record of id `id` with the values in values.
    // Can not be used in create().
    // (1, id, values)
    const RELATION_UPDATE = 1;
    //
    // Removes the record of id `id` from the set, then deletes it.
    // (2, id, _)
    const RELATION_DELETE = 2;
    //
    // Removes the record of id `id` from the set, but does not delete it.
    // (3, id, _)
    const RELATION_REMOVE_LINK = 3;
    //
    // Adds an existing record of id `id` to the set.
    // (4, id, _)
    const RELATION_ADD_LINK = 4;
    //
    // Removes all records from the set.
    // (5, _, _)
    const RELATION_REMOVE_ALL_LINKS = 5;
    //
    // Replaces all existing links with a new set.
    // (6, _, ids)
    const RELATION_REPLACE_ALL_LINKS = 6;

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
     * The last response.
     */
    protected $response;

    /**
     * List of model name mappings to model classes.
     */
    protected $modelMapping = [];

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
     * TODO: turn this into a factory for a connector that wraps
     * the XML RPC client, injected into OdooClient.
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
            $this->response = $xmlRpcClient->send($msg);
        } catch (Exception $e) {
            // Some connection problem.

            throw new Exception(sprintf(
                'Cannot connect to Odoo database "%s"',
                $this->config['database']
            ), null, $e);
        }

        // Grab the User ID.

        $this->userId = $this->valueToNative($this->response->value());

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

    public function arrayValue(iterable $data)
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

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        if ($this->response->value() instanceof Value) {
            return collect($this->valueToNative($this->response->value()));
        }

        // An error in the criteria or model provided.

        throw new Exception(sprintf(
            'Failed to search model %s; response was "%s"',
            $modelName,
            $this->response->value()
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

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        return $this->valueToNative($this->response->value());
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

            $ids = $this->search(
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

            $this->response = $this->getXmlRpcClient('object')->send($msg);
        }

        return $this->response;
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
        $this->response = $this->searchRead(
            $modelName,
            $criteria,
            $offset,
            $limit,
            $order
        );

        return $this->valueToNative($this->response->value());
    }

    /**
     * @param string $modelName example res.partner
     * @param array $instanceIds list of model instance IDs to read and return
     * @param array $options varies with API versions see documentation
     * @return Response
     */
    public function read(
        string $modelName,
        iterable $instanceIds = [],
        array $options = []
    ) {
        $msg = $this->getBaseObjectRequest($modelName, 'read');

        $msg->addParam($this->nativeToValue($instanceIds));

        if (! empty($options)) {
            $msg->addParam($this->nativeToValue($options));
        }

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        if ($this->response->value() instanceof Value) {
            $data = $this->valueToNative($this->response->value());

            $modelName = $this->mapModelName($modelName);

            return collect($data)->map(function ($item) use ($modelName) {
                return new $modelName($item);
            });
        }

        // An error in the instanceIds or model provided.

        throw new Exception(sprintf(
            'Failed to read model %s; response was "%s"',
            $modelName,
            $this->response->value()
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

        $this->response = $this->getXmlRpcClient('common')->send($msg);

        return $this->valueToNative($this->response->value());
    }

    /**
     * Create a new resource.
     *
     * @param array $fields 
     */
    public function create(string $modelName, array $fields)
    {
        $msg = $this->getBaseObjectRequest($modelName, 'create');

        $msg->addParam($this->nativeToValue($fields));

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        // If there was an error, then an integer will be returned.

        if (! $this->response->value() instanceof Value) {
            return $this->response->value();
        }

        return $this->valueToNative($this->response->value());
    }

    /**
     * Update a resource.
     *
     * @return bool true if the update was successful.
     */
    public function write(string $modelName, int $resourceId, array $fields)
    {
        $msg = $this->getBaseObjectRequest($modelName, 'write');

        $msg->addParam($this->nativeToValue([$resourceId]));
        $msg->addParam($this->nativeToValue($fields));

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        // If there was an error, then an integer will be returned.

        if (! $this->response->value() instanceof Value) {
            return $this->response->value();
        }

        return $this->valueToNative($this->response->value());
    }

    /**
     * Remove a resource.
     *
     * @return bool true if the removal was successful.
     */
    public function unlink(string $modelName, int $resourceId)
    {
        $msg = $this->getBaseObjectRequest($modelName, 'unlink');

        $msg->addParam($this->nativeToValue([$resourceId]));

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        // If there was an error, then an integer will be returned.

        if (! $this->response->value() instanceof Value) {
            return $this->response->value();
        }

        return $this->valueToNative($this->response->value());
    }

    /**
     * Get a list of fields for a resource.
     * TODO: there are some more parameters to define the context more.
     *
     * @return Collection of arrays (may be collection of models later)
     */
    public function fieldsGet(string $modelName)
    {
        $msg = $this->getBaseObjectRequest($modelName, 'fields_get');

        $this->response = $this->getXmlRpcClient('object')->send($msg);

        // If there was an error, then an integer will be returned.

        if (! $this->response->value() instanceof Value) {
            return $this->response->value();
        }

        return $this->valueToNative($this->response->value());
    }

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
        $resourceIds = $this->getResourceIds([$externalId], $model);

        return $resourceIds->first();
    }

    /**
     * Get multiple resource IDs at once.
     *
     * @param array $externalIds each either "name" or "module.name"
     * @param string $module optional, but recommended
     * @return collection
     *
     * FIXME: all external IDs must have the same "module" at the moment.
     */
    public function getResourceIds(
        iterable $externalIds,
        string $model = null,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $order = ''
    ) {
        $criteria = [];

        if ($model !== null) {
            $criteria[] = ['model', '=', 'res.partner'];
        }

        $moduleList = [];

        foreach($externalIds as $externalId) {
            if (strpos($externalId, '.') !== false) {
                list ($module, $name) = explode('.', $externalId, 2);
            } else {
                $name = $externalId;
                $module = '{none}';
            }

            if (! array_key_exists($module, $moduleList)) {
                $moduleList[$module] = [];
            }

            $moduleList[$module][] = $name;
        }

        // TODO: work out how to represent the boolean OR operator
        // for multiple modules fetched at once.
        // Each set of conditions in this loop should be ORed with
        // every other set of conditions in this loop.
        // So we should be able to search for "foo.bar_123" and "fing.bing_456"
        // in one query, giving us conceptually:
        // ((module = foo and name = bar_123) or (module = fing and name = bing_456))

        foreach($moduleList as $module => $externalIds) {
            if ($module !== '{none}') {
                $criteria[] = ['module', '=', $module];
            }

            $criteria[] = ['name', 'in', $externalIds];
        }

        $irModelDataIds = $this->search(
            'ir.model.data',
            $criteria,
            $offset,
            $limit,
            $order
        );

        if (empty($irModelDataIds)) {
            // No matches found, so give up now.

            return collect();
        }

        // Now read the full records to get the resource IDs.

        $irModelData = $this->read(
            'ir.model.data',
            $irModelDataIds
        );

        if ($irModelData === null) {
            // We could not find the record.
            // (We really should have, since we just looked it up)

            return collect();
        }

        // Return the resource IDs.

        return $irModelData->map(function ($item) {
            return $item->get('res_id');
        });
    }

    /**
     * Map the model name (e.g. "res.partner") to the model class
     * it will be instantiated into.
     * TODO: turn this into a factory that can be injected,
     */
    protected function mapModelName(string $modelName)
    {
        if (array_key_exists($modelName, $this->modelMapping)) {
            return $this->modelMapping[$modelName];
        }

        // Default fallback.

        return Model::class;
    }

    /**
     * Add multiple module name to model class mapping entries.
     */
    public function addModelMap(array $modelMap)
    {
        foreach ($modelMap as $modelName => $className) {
            $this->addModelMapping($modelName, $className);
        }

        return $this;
    }

    /**
     * Add a single module name to model class mapping entry
     */
    public function addModelMapping(string $modelName, string $className = null)
    {
        if ($className !== null) {
            $this->modelMapping[$modelName] = $className;
        } else {
            $this->removeModelMapping($modelName);
        }

        return $this;
    }

    /**
     * Remove a module name to model class mapping entry.
     */
    public function removeModelMapping(string $modelName)
    {
        unset($this->modelMapping[$modelName]);

        return $this;
    }

    /**
     * Return a message with the base parameters for any object call.
     * Identified the login credentials, model and action.
     * TODO: this should go in the connector factory.
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
     * Adds a new record created from the provided value dict.
     *
     * @param array $values
     * @return array
     */
    public function relationCreate(array $values)
    {
        return [[
            static::RELATION_CREATE, 0, $values
        ]];
    }

    /**
     * Updates an existing record of id `id` with the values in values.
     * Can not be used in create().
     *
     * @param int $resourceId the resource to update
     * @param array $values
     * @return array
     *
     * TODO: as well as an array of values, accept a model.
     * The model, ideally, would be able to provide a list of all the
     * fields that have changed so that only those are updated.
     * Extending that into relations within the field list would be
     * a bit more involved though.
     */
    public function relationUpdate(int $resourceId, array $values)
    {
        return [[
            static::RELATION_UPDATE, $resourceId, $values
        ]];
    }

    /**
     * Removes the record of id `id` from the set, then deletes it
     * (from the database).
     * Can not be used in create().
     *
     * @param int $resourceId the resource to be removed from the database
     * @return array
     */
    public function relationDelete(int $resourceId)
    {
        return [[
            static::RELATION_DELETE, $resourceId, 0
        ]];
    }

    /**
     * Removes the record of id `id` from the set, but does not delete it.
     * Can not be used on one2many.
     * Can not be used in create().
     *
     * @param int $resourceId the resource to be removed from the link
     * @return array
     */
    public function relationRemoveLink(int $resourceId)
    {
        return [[
            static::RELATION_REMOVE_LINK, $resourceId, 0
        ]];
    }

    /**
     * Creates data structure for setting relationship details.
     * Adds an existing record of id `id` to the set.
     * Can not be used on one2many.
     *
     * @param int $resourceId the resource to be added to the link
     * @return array
     */
    public function relationAddLink(int $resourceId)
    {
        return [[
            static::RELATION_ADD_LINK, $resourceId, 0
        ]];
    }

    /**
     * Removes all records from the set, equivalent to using the
     * command 3 on every record explicitly.
     * Can not be used on one2many.
     * Can not be used in create().
     *
     * @return array
     */
    public function relationRemoveAllLinks()
    {
        return [[
            static::RELATION_REMOVE_ALL_LINKS, 0, 0
        ]];
    }

    /**
     * Replaces all existing records in the set by the ids list,
     * equivalent to using the command 5 followed by a command 4
     * for each id in ids.
     * Can not be used on one2many.
     *
     * @return array
     */
    public function relationReplaceAllLinks(iterator $resourceIds)
    {
        return [[
            static::RELATION_REPLACE_ALL_LINKS, 0, $resourceIds
        ]];
    }


    /**
     * Walk through the criteria array and convert scalar values to
     * XML-RPC objects, and nested arrays to array and struct objects.
     */
    public function nativeToValue($item)
    {
        // If a scalar, then map to the appropriate object.

        if (! is_iterable($item)) {
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

        // If an iterable, then deal with the children first.

        foreach ($item as $key => $element) {
            $item[$key] = $this->nativeToValue($element);
        }

        // Map to an array or a struct, depending on whether a numeric
        // keyed array or an associative array is to be encoded.

        if ($item === []
            || (is_array($item) && array_keys($item) === range(0, count($item) - 1))
            || (is_iterable($item) && ! is_array($item))
        ) {
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

    /**
     * The last response, in case it needs to be inspected for
     * error reasons.
     *
     * @return Response|null
     */
    public function getLastResponse()
    {
        return $this->response;
    }
}

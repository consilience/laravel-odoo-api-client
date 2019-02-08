# Consilience Laravel Odoo XML RPC Client

Tested against Laravel 5.7 and Odoo 7 (OpenERP 7).

More docs to do, examples to write, and the write interfaces to produce.

# Example

A very simple example:

```php
// The facade.

use OdooApi;

// The default config.
// getClient() will take other configuration names.

$client = OdooApi::getClient();

// Note the criteria is a nested list of scalar values.
// The datatypes will converted to the appropriate objects internally.
// You can mix scalars and objects here to force the datatype, for example
// ['name', $client->stringValue('ilike'), 'mich']

$criteria = [
    [
        'name',
        'ilike',
        'mich'
    ]
];

// First 10 matching IDs

$client->search('res.partner', $criteria, 0, 10, 'id desc')->value()->me['array']

// Total count for the criteria.

$client->searchCount('res.partner', $criteria);

// Read the complete details of two specific partners.

$client->read('res.partner', [17858, 17852])->value()->me['array']
```

# TODO

* An elegant way to parse the results, as the `Value` objects can be
  a little cumbersome.
  For example, we know the `search()` result will be an array of
  integer model instance IDs, so a simple array or collection of IDs can
  be returned, rather than a Value array containing Value integer objects.
* Docs on config (this package supports multiple clients, so can connect
  to multiple Odoo instances or as multiple users at the same time).
* Docs on installation (has a auto discovered provider and facade).
  Includes details on how to publish the config file.
* The write functions are not written yet (create, write and unlink).
* The search_read method is not supported yet.
* The setting of the configuration could be done in a more fluent
  way, as tends to be the Laravel way. But it's no biggie.

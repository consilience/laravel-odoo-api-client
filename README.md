# Consilience Laravel Odoo XML RPC Client

Tested against Laravel 5.7 and Odoo 7 _(OpenERP 7)_.

# Installation

Through composer:

    composer require consilience/laravel-odoo-api-client

Note: pending release to packagist, the following entry in `composer.json`
is needed to locate this package:

```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/consilience/laravel-odoo-api-client.git"
        }
        ...
    ]
    ...
```

# Publishing the Configuration

Publish `config\odoo-api.php` using the Laravel artisan command:

    artisan vendor:publish --provider="Consilience\OdooApi\OdooServiceProvider"

A sample set of entries for `.env` can be found in `.env.example`.

# Example

A very simple example:

```php

// This facade is auto-discoverec for Laravel 5.6+

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

# Query methods

The following methods are supported and will return an XML-RPC response:

* search()
* searchRead()
* read()

The following helper functions return a native PHp type insead:

* searchArray - array
* searchReadArray - array
* readArray - array
* searchCount - integer
* getResourceId - integer

Note that `searchRead` will emulate the server's `search_read` for
Odoo versions less than 8.0 (OpenERP) but use the native `search_read`
for Odoo 8.0 upwards.

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

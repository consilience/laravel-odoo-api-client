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

You can add multiple sets of configuration to `config\odoo-api.php` and
use them all at once in your application.
The configuration set name is passed in to `OdooApi::getClient('config-name')`.

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

If you have specific requirements for the XML-RPC client, such as an SSL
certificate to add, then yiu can get the client instance using:

    $xmlRpcClient = $client->getXmlRpcClient($type);

where `$type` will typically be 'db', 'common' or 'object'.

# Query methods

The following methods are supported and will return an XML-RPC response:

* search()
* searchRead()
* read()

The following helper functions return a native PHP type insead:

* searchArray - array
* searchReadArray - array
* readArray - array
* searchCount - integer
* getResourceId - integer
* getResourceIds - array

(I'm torn between this approach and a more fluent approach such as
`$client->firstOnly()->asArray()->read(...)` to set the context that
will apply to the next command.)

Note that `searchRead` will emulate the server's `search_read` for
Odoo versions less than 8.0 (OpenERP) but use the native `search_read`
for Odoo 8.0 upwards.

# Read Options

The `read()` method takes an options array that varies significantly
between OpenERP/Odoo versions.
This package does not attempt to deal with that at this time.

Fpor example, to restrict the read to named attributes, the following
formats are used:

* OpenERP 7: $client->read('account.invoice', [123], ['type', 'partner_id']);
* OpenERP 8: $client->read('account.invoice', [123], ['fields' => ['type', 'partner_id']]);
* OpenERP 10: $client->read('account.invoice', [123], ['attributes' => ['type', 'partner_id']]);

This makes finding help on the API difficult, since many articles
fail to make the OpenERP/Odoo version number clear.

# TODO

* The write functions are not written yet (~~create~~, ~~write~~ and unlink).
* Examples on how relationships are managed are needed, since they are
  one of the areas that cause the most confusion. It's actuall pretty
  easy once you see the technique, though a helper may be useful to
  put together the data structure needed.

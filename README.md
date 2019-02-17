# Consilience Laravel Odoo XML RPC Client

Tested against Laravel 5.7 and Odoo 7 _(OpenERP 7)_.

# Introduction

The aim of this package is to provide easy access to the
OpenERP/Odoo XML-RPC API from within Laravel.
Just set up some config, get a client from the `OdooApi`
facade, and throw it some data.

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

// This facade is auto-discovered for Laravel 5.6+

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

You have the ability to construct your own messages from scratch like this,
and there are helper methods in the `$client` to convert native PHP data types
to and from XML RPC value objects.
However, you should be able to leave all that conversion to be handled in the
background by the client - just give it array/string/in/etc. data and get
models and arrays back.

# Query methods

The following methods are supported and will return a collection:

* search() - collection of integers
* searchRead() - collection of models
* read() - collection of models
* getResourceIds - collection of integers
* fieldsGet() - collection of arrays

The following helper functions return a native PHP type insead:

* searchCount - integer
* getResourceId - integer
* unlink - boolean
* create - integer

All `read()` and `searchRead()` methods will return a collection of models.
The default model will be `Consilience\OdooApi\Model`, but other models can be specified.
The `odoo-api.php` config provides an array setting to map OpenERP model names
to model class names for instantiation. Further mappings can be added to the client
using `$client->addMapping('odoo.model.name', \FQDN\Class\name::class)`.

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

# Setting Relationships

There are helpers to create the relationships data.
Just a simple example, replacing all invoices belonging to a
partner witn a new set of invoices:

```php
$invoiceIds = ... // array or collection of resource IDs for the invoices to link

$response = $client->write(
    'res.partner',
    $partnerResourceId,
    [
        'invoice_ids' => $client->relationReplaceAllLinks($invoiceIds),
        // other optional fields and relations can be set here too
    ]
);
```

# TODO

* Conversion of date types has not been tested.
  Ideally we would support Carbon 2 for sending dates in and getting
  dates back out again.
* Tests. It's always the tests that get left behind when time gets in
  the way. They need to run on a Laravel context, so helpers needed for
  that.
* Would be nice to split this up into a non-laravel package and then
  add a separate laravel wrapper for it. But collections are just too
  nice, so this may not happen.


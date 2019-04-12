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
certificate to add, then you can get the client instance using:

    $xmlRpcClient = $client->getXmlRpcClient($type);

where `$type` will typically be 'db', 'common' or 'object'.

You have the ability to construct your own messages from scratch like this,
and there are helper methods in the `$client` to convert native PHP data types
to and from XML RPC value objects.
However, you should be able to leave all that conversion to be handled in the
background by the client - just give it array/string/int/etc. data and get
models and arrays back.

# Query methods

The following methods are supported and will return a collection:

* search() - collection of integers
* searchRead() - collection of models
* read() - collection of models
* getResourceIds - collection of integers
* fieldsGet() - collection of arrays

The following helper functions return a native PHP type instead:

* searchCount - integer
* getResourceId - integer
* unlink - boolean
* create - integer
* write - boolean

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

For example, to restrict the read to named attributes, the following
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
        
        // other optional fields and relations can be set here as nornmal
    ]
);
```

The general way to set a relationship is to set the relation (`invoice_ids` in this
case) to a data structure which contains a list of IDs and instructions on
what to do with those IDs.

The `relationReplaceAllLinks()` here generates the data structure to instruct Odoo
to replace all links between the `res.partner` and any invoices they have, with
the new list of `$invoiceIds` (an array).
You can construct those data structures yourself, or use the following helpers:

```php
// Relate a resource.
$client->relationCreate(array $resourceIds)

// Update a related resource.
// e.g. change the product on an invoice line for an invoice
relationUpdate(int $resourceId, array $values)

// Delete a related resource completely.
// e.g. delete an invoice line on an invoice
relationDelete(int $resourceId)

// Remove the relation to a related resource, but leave the resource intact.
// e.g. remove an invoice from a contact so it can be adde to a new contact
relationRemoveLink(int $resourceId)

// Add a resource to a relation, leaving existing relations intact.
// e.g. add an additional line to an invoice. 
relationAddLink(int $resourceId)

// Remove all relations to a resource type.
// e.g. remove all invoices from a contact, before the contatc can is deleted.
relationRemoveAllLinks()

// Replace all relations with a new set of relations.
// e.g. remove all invoices from contact, and give them a new bunch of invoices
// to be responsible for. 
relationReplaceAllLinks(iterable $resourceIds)
``` 

# Non-CRUD Requests

There are helper functions to provide `read`, `write`, `unlink`, `search` functionality,
but you also have access to other API methods at a lower level.
For example, a note can be added to a sales invoice using the `message_post` function
for a sales order.
The example below shows how.

```php
use OdooApi;

$client = OdooApi::getClient();

// Resource and action, the remote RPC function.
// Note that the message_post() function for each resource type is
// different, i.e. this is not something that can be genereralised
// in the API.
// This starts to build the request message and addes the first
// few positional parameters and authentication details.

$msg = $client->getBaseObjectRequest('sale.order', 'message_post');

// Further positional parameters.
// This is for an Odoo 7.0 installation. Other versions may differ.

$msg->addParam($client->nativeToValue([$orderId])); // Resource(s) ID
$msg->addParam($client->nativeToValue($text_message)); // Body
$msg->addParam($client->nativeToValue(false)); // Subject
$msg->addParam($client->nativeToValue('comment')); // Subtype
$msg->addParam($client->nativeToValue(false)); // Partner IDs to send a copy to

// Send the message.

$response = $client->getXmlRpcClient('object')->send($msg);

// If you want to inspect the result, then this will give you
// what the Odoo message_post() function returns.

$result = $client->valueToNative($response->value());
``` 

# TODO

* Conversion of `date` types have not been tested.
  Ideally we would support Carbon 2 for sending dates in and getting
  dates back out again.
* Tests. It's always the tests that get left behind when time gets in
  the way. They need to run on a Laravel context, so helpers needed for
  that.
* Would be nice to split this up into a non-laravel package and then
  add a separate laravel wrapper for it. But collections are just too
  nice, so this may not happen.
* Helper methods for some of the Odoo version specific data structures.
  For example, specifying the list of fields to retrieve for a `read`
  has new structures introduced for versions 7, 8 and 10.
  The client class is also going to star getting a bit cumbersome at this
  point, so moving some of the XML-RPC specific stuff (message creation, data
  conversion) would be best moved to a separate connection class).
* Positional parameter builder helper.

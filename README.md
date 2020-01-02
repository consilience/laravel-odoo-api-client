# Consilience Laravel Odoo XML RPC Client

Tested against Laravel 5.7 and Odoo 7 _(OpenERP 7)_.

[![Latest Stable Version](https://poser.pugx.org/consilience/laravel-odoo-api-client/v/stable)](https://packagist.org/packages/consilience/laravel-odoo-api-client)
[![Total Downloads](https://poser.pugx.org/consilience/laravel-odoo-api-client/downloads)](https://packagist.org/packages/consilience/laravel-odoo-api-client)
[![Latest Unstable Version](https://poser.pugx.org/consilience/laravel-odoo-api-client/v/unstable)](https://packagist.org/packages/consilience/laravel-odoo-api-client)
[![License](https://poser.pugx.org/consilience/laravel-odoo-api-client/license)](https://packagist.org/packages/consilience/laravel-odoo-api-client)

# Introduction

The aim of this package is to provide easy access to the
OpenERP/Odoo XML-RPC API from within Laravel.
Just set up some config, get a client from the `OdooApi`
facade, and throw it some data.

Ideally this would be in two parts: an API package and the
Laravel wrapper.
That can still be done later, but for now this meets our
requirements and some helpers provided by laravel make things
run a lot smoother (collections, array/object dot-notation access).

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
    ['name', 'ilike', 'mich'],
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

# Search Criteria

The search criteria is an array of search terms and logic operators,
expressed in Polish Notation.

The logic operators, for comparing search terms, are:

* `&` - logical AND
* `|` - logical OR
* `!` - logical NOT

Each search term is a tuple of the form:

    [field_name, operator, value]
    
The search term operators are:

* =
* !=
* \>
* \>=
* <
* <=
* like
* ilike
* in
* not in
* child_of - records who are children or grand-children of a given record,
* parent_left
* parent_right

Example: search for a record where the name is like 'Fred%' or 'Jane%'
and the partner ID is 1 or 2, would look like this:

```php
[
    '&',
    '|',
    ['name', 'like', 'Fred%'],
    ['name', 'like', 'Jane%'],
    ['partner_id', 'in', [1, 2]],
]
```

The Polish Notation works inner-most to outer-most.
The first `&` operator takes the next two terms and 'AND's them.
The first of the two terms is a `|` operator.
The `|` operator then takes the next two terms and 'OR`s them,
making a single condition as a result, which is fed to the 'AND'.
The final term is fed to the 'AND' condition.
The result is equivalent to:

```sql
(name like 'Fred%' or name like 'Jane%') and partner_id in (1, 2)
```

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

# Load Function

Odoo offers a loader API that handles resource loading easily.
This package offers the `load()` and `loadOne()` methods to
access that API.

The loader uses `id` as the external ID.
It will find the resource if it already exists and update it,
otherwise it will create the resource if it does not exist.

Each resource in the list can be specified with different fields,
but all must be for the same resource model.

```php
    // Load one or more partners.

    $loadResult = $client->load('res.partner', [
        [
            "name" => "JJ Test",
            "active" => "TRUE",
            "type" => "developer",
            "id" => "external.partner_12345",
        ],
        // Further records for this model...
    ]);
```

The response will be an array with two elements, `ids` and `messages`,
both collections.

The `ids` collection will contain the *internal* IDs of any resources updated
or created.
The `messages` collection will contain any validation errors for failed
updates or resource creation.
There may be multiple messages for a single failed record.

```php
// Example response with no errors and two resources updated or created.

array:2 [
  "ids" => Collection {
    #items: array:2 [
      0 => 7252
      1 => 7251
    ]
  }
  "messages" => Collection {
    #items: []
  }
]

// Example with oen validation error.
// Note no records are loaded at all if any record fails validation.

array:2 [
  "ids" => Collection {
    #items: []
  }
  "messages" => Collection {
    #items: array:1 [
      0 => array:5 [
        "field" => "year"
        "rows" => array:2 [
          "to" => 1
          "from" => 1
        ]
        "record" => 1
        "message" => "'2019x' does not seem to be an integer for field 'My Year'"
        "type" => "error"
      ]
    ]
  }
]
```

Although the record keys can vary between records,
the Odoo API does not support that internally.
This package works around that by grouping the records with
identical keys and loading them in groups.
This means that a validation error in one group will not
prevent records loading from another group, so the result
can be a mix of failed and loaded records.

An exception will be thrown on unrecoverable errors in Odoo,
such as a database integrity constraint violation.

The `loadOne()` method works in a similar way,
but accepts just one record to load.
It will return an `id` element with the integer `internal ID`
or `null` if the record failed to load, along with a collection
for the messages.

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
* Some more explicit exceptions.

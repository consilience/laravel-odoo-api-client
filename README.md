# Consilience Laravel Odoo XML RPC Client

Tested against Laravel 5.7 and Odoo 7.

More docs to do, examples to wite, and the write interfaces to produce.

# Example

A very simple example:

```php
use OdooApi;

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

// First 10 macthing IDs

$client->search('res.partner', $criteria, 0, 10, 'id desc')->value()->me['array']

// Total count for the criteria.

$client->searchCount('res.partner', $criteria);
```

# TOOD

An elegant way to parse the results, as the `Value` objects can be
a little cumbersome.
For example, we know the `search()` result will be an array of
integer model instance IDs, so a simple array of IDs can be returned,
rather than a Value array containing Value integer objects.

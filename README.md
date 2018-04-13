# Silverstripe dataobjecthistory
Adds a history tab to dataobjects

## Installation
Composer is the recommended way of installing SilverStripe modules.
```
composer require gorriecoe/silverstripe-dataobjecthistory
```

## Requirements

- silverstripe/framework ^4.0
- symbiote/silverstripe-gridfieldextensions ^3.1

## Maintainers

- [Gorrie Coe](https://github.com/gorriecoe)

## Example

```php
<?php

use SilverStripe\Versioned\Versioned;
use gorriecoe\DataObjectHistory\extensions\DataObjectHistory;

class MyObject extends DataObject
{
    private static $extensions = [
        Versioned::class . '.versioned',
        DataObjectHistory::class
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create();
        ...
        $this->extend('updateCMSFields', $fields); // Required
        return $fields;
    }
}
```

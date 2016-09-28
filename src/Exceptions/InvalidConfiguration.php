<?php

namespace Spatie\Activitylog\Exceptions;

use Exception;
use Spatie\Activitylog\Models\Activity;

class InvalidConfiguration extends Exception
{
    public static function modelIsNotValid(string $className)
    {
        return new static("The given model class `$className` does not extend `".Activity::class.'`');
    }

    public static function causedByResolverIsNotValid()
    {
        return new static('The given caused_by_resolver is not a valid closure');
    }
}

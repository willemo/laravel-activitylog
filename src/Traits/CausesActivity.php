<?php

namespace Spatie\Activitylog\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

trait CausesActivity
{
    public function causedActivity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'causer');
    }

    /** @deprecated Use causedActivity() instead */
    public function loggedActivity(): MorphMany
    {
        return $this->causedActivity();
    }
}

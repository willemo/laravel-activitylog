<?php

namespace Spatie\Activitylog;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use Spatie\Activitylog\Exceptions\CouldNotLogActivity;
use Spatie\Activitylog\Exceptions\InvalidConfiguration;
use Spatie\Activitylog\Models\Activity;

class ActivityLogger
{
    use Macroable;

    /** @var \Illuminate\Auth\AuthManager */
    protected $auth;

    protected $logName = '';

    /** @var bool */
    protected $logEnabled;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $performedOn;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $causedBy;

    /** @var \Illuminate\Support\Collection */
    protected $properties;

    public function __construct(AuthManager $auth, Repository $config)
    {
        $this->auth = $auth;

        $this->properties = collect();

        $this->causedBy = $this->resolveCauser($auth, $config);

        $this->logName = $config['laravel-activitylog']['default_log_name'];

        $this->logEnabled = $config['laravel-activitylog']['enabled'] ?? true;
    }

    public function performedOn(Model $model)
    {
        $this->performedOn = $model;

        return $this;
    }

    public function on(Model $model)
    {
        return $this->performedOn($model);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @return $this
     */
    public function causedBy($modelOrId)
    {
        $model = $this->normalizeCauser($modelOrId);

        $this->causedBy = $model;

        return $this;
    }

    public function by($modelOrId)
    {
        return $this->causedBy($modelOrId);
    }

    /**
     * @param array|\Illuminate\Support\Collection $properties
     *
     * @return $this
     */
    public function withProperties($properties)
    {
        $this->properties = collect($properties);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function withProperty(string $key, $value)
    {
        $this->properties->put($key, $value);

        return $this;
    }

    public function useLog(string $logName)
    {
        $this->logName = $logName;

        return $this;
    }

    public function inLog(string $logName)
    {
        return $this->useLog($logName);
    }

    /**
     * @param string $description
     *
     * @return null|mixed
     */
    public function log(string $description)
    {
        if (! $this->logEnabled) {
            return;
        }

        $activityModelClassName = $this->determineActivityModel();

        $activity = new $activityModelClassName();

        if ($this->performedOn) {
            $activity->subject()->associate($this->performedOn);
        }

        if ($this->causedBy) {
            $activity->causer()->associate($this->causedBy);
        }

        $activity->properties = $this->properties;

        $activity->description = $this->replacePlaceholders($description, $activity);

        $activity->log_name = $this->logName;

        $activity->save();

        return $activity;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @throws \Spatie\Activitylog\Exceptions\CouldNotLogActivity
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function normalizeCauser($modelOrId): Model
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        if ($model = $this->auth->getProvider()->retrieveById($modelOrId)) {
            return $model;
        }

        throw CouldNotLogActivity::couldNotDetermineUser($modelOrId);
    }

    protected function replacePlaceholders(string $description, Activity $activity): string
    {
        return preg_replace_callback('/:[a-z0-9._-]+/i', function ($match) use ($activity) {
            $match = $match[0];

            $attribute = (string) string($match)->between(':', '.');

            if (! in_array($attribute, ['subject', 'causer', 'properties'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = $activity->$attribute;

            $attributeValue = $attributeValue->toArray();

            return array_get($attributeValue, $propertyName, $match);
        }, $description);
    }

    /**
     * @throws \Spatie\Activitylog\Exceptions\InvalidConfiguration
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function determineActivityModel()
    {
        $activityModel = config('laravel-activitylog.activity_model') ?? Activity::class;

        if (! is_a($activityModel, Activity::class, true)) {
            throw InvalidConfiguration::modelIsNotValid($activityModel);
        }

        return $activityModel;
    }

    /**
     * @param  \Illuminate\Auth\AuthManager            $auth
     * @param  \Illuminate\Contracts\Config\Repository $config
     *
     * @throws \Spatie\Activitylog\Exceptions\InvalidConfiguration
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function resolveCauser(AuthManager $auth, Repository $config)
    {
        $causerResolver = $config['laravel-activitylog']['caused_by_resolver'];

        $reflection = new \ReflectionFunction($causerResolver);

        if (! $reflection->isClosure()) {
            throw InvalidConfiguration::causedByResolverIsNotValid();
        }

        return call_user_func_array($causerResolver, [$auth, $config]);
    }
}

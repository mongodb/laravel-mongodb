<?php

namespace Jenssegers\Mongodb\Eloquent\Concerns;

trait HasTimestamps
{
    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    public function updateTimestamps()
    {
        $time = $this->freshTimestamp();

        if (! is_null(static::UPDATED_AT) && ! $this->isDirty(static::UPDATED_AT)) {
            $this->setUpdatedAt($time);
        }

        if (! $this->exists && ! $this->isDirty(static::CREATED_AT)) {
            $this->setCreatedAt($time);
        }
    }
}
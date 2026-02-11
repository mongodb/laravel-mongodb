<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Database\Query\Builder;

use function method_exists;

/**
 * The method {@see \Illuminate\Database\Query\Builder::timeout()} was added in
 * Laravel 12.51.0. On Laravel 12.51.0 and later, this trait is empty because the
 * framework already provides the method. On older Laravel versions, this trait
 * defines the timeout API to provide backwards compatibility.
 */
if (method_exists(Builder::class, 'timeout')) {
    /** @internal For Laravel 12.51+ */
    trait BuilderTimeout
    {
    }
} else {
    /** @internal For older Laravel versions */
    trait BuilderTimeout
    {
        /**
         * The maximum amount of seconds to allow the query to run.
         *
         * @var int|float
         */
        public $timeout;

        /**
         * The maximum amount of seconds to allow the query to run.
         *
         * @param  int|null $seconds
         *
         * @return $this
         */
        public function timeout($seconds)
        {
            $this->timeout = $seconds;

            return $this;
        }
    }
}

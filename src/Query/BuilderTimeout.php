<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Database\Query\Builder;

use function method_exists;

/**
 * The method {@see \Illuminate\Database\Query\Builder::timeout()} was added in
 * Laravel 12.51.0. This trait removes the method if it already exists
 * as it's identical to the one added in Laravel 12.51, and adds it if it doesn't
 * exist to provide support for older Laravel versions.
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
         * @param  int|float $seconds
         *
         * @return $this
         */
        public function timeout($seconds): static
        {
            $this->timeout = $seconds;

            return $this;
        }
    }
}

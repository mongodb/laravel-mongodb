<?php

namespace Jenssegers\Mongodb\Helpers;

class Obj
{
    /**
     * Check if an item or items exist in an array of objects using "dot" notation.
     *
     * @param  object|array  $object
     * @param  string|array  $keys
     * @return bool
     */
    public static function has($object, $keys)
    {
        if (is_null($keys) || empty($keys)) {

            return false;
        }

        if (empty($object)) {

            return false;
        }

        $key = array_shift($keys);

        if (static::exists($object, $key)) {

            if (empty($keys)) {

                return true;
            }

            return static::has(static::getValue($object, $key), $keys);
        }

        return false;
    }

    /**
     * Get a value of a given object or array
     *
     * @param object|array $object
     * @param string $key
     * @return array|object|null
     */
    public static function getValue($object, $key)
    {
        if (is_object($object)) {

            return $object->$key;
        }

        if (is_array($object)) {

            return $object[$key];
        }

        return null;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  object|array  $object
     * @param  string|int  $key
     * @return bool
     */
    public static function exists($object, $key)
    {
        if (is_array($object)) {

            return array_key_exists($key, $object);
        }

        if (is_object($object)) {

            return property_exists($object, $key);
        }

        return false;
    }

    public static function get($object, $keys)
    {
        if (is_null($keys) || empty($keys)) {

            return false;
        }

        if (empty($object)) {

            return false;
        }

        $key = array_shift($keys);

        if (static::exists($object, $key)) {

            if (empty($keys)) {

                return static::getValue($object, $key);
            }

            return static::get(static::getValue($object, $key), $keys);
        }

        return null;
    }

    public static function iterator_to_object($cursor)
    {
        $result = [];

        foreach ($cursor as $item) {

            if (is_array($item)) {

                $result[] = static::array_to_object($item);

            } else {

                $result[] = $item;
            }
        }

        return $result;
    }

    public static function array_to_object(array $array)
    {
        if (empty($array) || array_keys($array) === range(0, count($array) - 1)) {

            $object = [];

            foreach ($array as $item) {

                if (is_array($item)) {

                    $object[] = static::array_to_object($item);

                } else {

                    $object[] = $item;
                }
            }

        } else {

            $object = new \stdClass();

            foreach ($array as $key => $item) {

                if (is_array($item)) {

                    $object->$key = static::array_to_object($item);

                } else {

                    $object->$key = $item;
                }
            }
        }

        return $object;
    }
}
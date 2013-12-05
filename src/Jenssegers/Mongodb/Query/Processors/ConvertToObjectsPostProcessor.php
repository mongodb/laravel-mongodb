<?php namespace Jenssegers\Mongodb\Query\Processors;

use  Illuminate\Database\Query\Builder;
use  Illuminate\Database\Query\Processors\Processor;

class ConvertToObjectsPostProcessor extends Processor {

        /**
         * Process the results of a "select" query into objects when applicable.
         * This will obviously have an impact on processing large results but will 
         * fix the issues described in:
         *
         * https://github.com/jenssegers/Laravel-MongoDB/issues/65
         * https://github.com/jenssegers/Laravel-MongoDB/issues/78
         *
         * @param  \Jenssegers\Mongodb\Builder $query
         * @param  array  $results
         * @return array
         */
        public function processSelect(Builder $query, $results)
        {

                //Walk each result as a possible source of convertible data
                foreach($results as &$resultingItem)
                {
                        $this->recursiveTransform($resultingItem);
                }

                //Return the transformed results
                return $results;
        }

        public function recursiveTransform(&$data)
        {

                //If the data is an array and doesn't contains only numeric keys
                //Filter out keys that are not numeric, if none, abort, they are a collection of sub items, not a sub document
                if(is_array($data) && count(array_filter(array_keys($data), function($item){
                        return !is_numeric($item);
                })) > 0)
                {

                        //Recursively process the sub items
                        foreach($data as &$subdata)
                        {
                                $this->recursiveTransform($subdata);
                        }
                        
                        //Now that it is processed, convert it
                        $data = (Object)$data;

                }
        }

}
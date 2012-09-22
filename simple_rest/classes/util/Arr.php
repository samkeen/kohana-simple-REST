<?php defined('SYSPATH') or die('No direct script access.');

class Util_Arr {

    static function prefix_array_key($prefix, $input)
    {
        $key_prefixed_array = array();
        foreach($input as $key => $value)
        {
            $key_prefixed_array[$prefix.$key] = $value;
        }
        return $key_prefixed_array;
    }
}
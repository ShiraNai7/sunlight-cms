<?php

namespace Sunlight\Util;

abstract class Request
{
    /**
     * Ziskat hodnotu z $_GET
     *
     * @param string $key klic
     * @param mixed $default vychozi hodnota
     * @param bool $allow_array povolit pole 1/0
     */
    static function get(string $key, $default = null, bool $allow_array = false)
    {
        if (isset($_GET[$key]) && ($allow_array || !is_array($_GET[$key]))) {
            return $_GET[$key];
        }

        return $default;
    }

    /**
     * Ziskat hodnotu z $_POST
     *
     * @param string $key klic
     * @param mixed $default vychozi hodnota
     * @param bool $allow_array povolit pole 1/0
     */
    static function post(string $key, $default = null, bool $allow_array = false)
    {
        if (isset($_POST[$key]) && ($allow_array || !is_array($_POST[$key]))) {
            return $_POST[$key];
        }

        return $default;
    }
}

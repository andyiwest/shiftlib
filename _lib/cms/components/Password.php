<?php

namespace cms\components;

use cms\Component;
use cms\ComponentInterface;

class Password extends Component implements ComponentInterface
{
    public $field_type = 'password';
    public $preserve_value = true;

    public function value($value, $name = ''): string
    {
        return '';
    }

    public function formatValue($value)
    {
        global $auth;

        // add 1 to max position
        if ($auth->hash_password) {
            $value = $auth->create_hash($value);
        }

        return $value ?: false;
    }
}
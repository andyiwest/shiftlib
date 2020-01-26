<?php

namespace cms\components;

use cms\Component;
use cms\ComponentInterface;

class Color extends Component implements ComponentInterface
{
    public $field_type = 'color';

    public function getFieldSql(): ?string
    {
        return "VARCHAR( 7 ) NOT NULL DEFAULT ''";
    }
}
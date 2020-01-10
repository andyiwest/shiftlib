<?php

namespace cms;

class rating extends component
{
    public $field_sql = 'TINYINT';

    // rating widget options
    public $rating_opts = [
        1 => 'Very Poor',
        2 => 'Poor',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent',
    ];

    public function field($field_name, $value = '', $options = [])
    {
        ?>
        <select name="<?= $field_name; ?>" class="rating" <?= $options['attribs']; ?>>
            <option value="">Choose</option>
            <?= html_options($this->rating_opts, $value, true); ?>
        </select>
        <?php
    }

    public function value($value, $name)
    {
        $field_name = underscored($name);
        
        $value = '<select name="' . $field_name . '" class="rating" disabled="disabled">
            <option value="">Choose</option>
            ' . html_options($this->rating_opts, $value, true) . '
        </select>';

        return $value;
    }

    public function search_field($name, $value)
    {
        return false;
    }
}

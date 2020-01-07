<?php

namespace cms;

class phpupload extends component
{
    public function field($field_name, $value = '', $options = [])
    {
        ?>
        <input type="text" name="<?= $field_name; ?>" class="upload" value="<?= $value; ?>">
        <?php
    }

    public function value($value, $name)
    {
        if ($value) {
            $value = '
                <img src="/_lib/phpupload/?func=preview&file=' . $value . '&w=320&h=240" id="' . $name . '_thumb"><br>
                <label id="' . $name . '_label">' . $value . '</label>
			';
        }
        return $value;
    }
}

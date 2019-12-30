<?php
namespace cms;

class time
{
	function field($field_name, $value = '', $options = []) {
        ?>
            <input type="time" step="1" data-type="time" id="<?=$field_name;?>" name="<?=$field_name;?>" value="<?=('00:00:00' != $value) ? substr($value, 0, -3) : '';?>" <?php if ($options['readonly']) { ?>disabled<?php } ?> <?=$options['attribs'];?> />
        <?php
	}
}
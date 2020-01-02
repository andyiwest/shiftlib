<?php
namespace cms;

abstract class component
{
	// used for input field
	public $field_type = 'text';
	
	// sql code for field creation
	public $field_sql = "VARCHAR( 140 ) NOT NULL DEFAULT ''";
	
	// bool: keep value when empty
	public $preserve_value = false;
	
	// bool: whether we need an id to save the value
	public $id_required = false;
	
	// returns the editable field
	function field($field_name, $value = '', $options = []) {
		?>
		<input type="<?=$this->field_type;?>" name="<?=$field_name;?>" value="<?=htmlspecialchars($value);?>" <?php if ($options['readonly']) { ?>disabled<?php } ?> <?php if ($options['placeholder']) { ?>placeholder="<?=$options['placeholder'];?>"<?php } ?> <?=$options['attribs'];?>>
		<?
	}
	
	// returns the display value
	function value($value, $name = '') {
		return $value;
	}
	
	// checks the value is valid
	function is_valid($value) {
		return true;
	}
	
	// applies any cleanup before saving value is mixed
	function format_value($value) {
		return strip_tags($value);
	}
	
	// generates sql code for use in where statement
	function conditions_to_sql($field_name, $value, $func = '', $table_prefix='') {
        $value = str_replace('*', '%', $value);
        return $table_prefix . $field_name . " LIKE '" . escape($value) . "'";
	}
	
	function search_field($name, $value) {
		$field_name = underscored($name);
	?>
	    <label for="<?=$field_name;?>" class="col-form-label"><?=ucfirst($name);?></label>
		<input type="text" class="form-control" name="<?=$field_name;?>" value="<?=$value;?>">
	<?php
	}
}
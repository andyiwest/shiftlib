<?php
namespace cms;

class email extends component{
	public $field_type = 'email';
	
	function value($value) {
		$value = '<a href="mailto:' . $value . '" target="_blank">' . $value . '</a>';
		return $value;
	}
	
	function is_valid($value) {
		return is_email($value);
	}
}
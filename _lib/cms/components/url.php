<?php
namespace cms;

class url extends component{
	function value($value) {
        $value = '<a href="' . $value . '" target="_blank">' . $value . '</a>';
		return $value;
	}
	
	function is_valid($value) {
		return is_url($value);
	}
}
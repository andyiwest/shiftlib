<?php
namespace cms;

class rating extends component
{
    // rating widget options
    public $rating_opts = [
        1 => 'Very Poor',
        2 => 'Poor',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent',
    ];
        
	function field($field_name, $value = '', $options = []) {
        ?>
            <select name="<?=$field_name;?>" class="rating" data-section="<?=$this->section;?>" data-item="<?=$this->content['id'];?>" <?php if ('avg-rating' == $type) {?>data-avg='data-avg'<?php } ?> <?=$attribs;?>>
                <option value="">Choose</option>
                <?=html_options($this->opts['rating'], $value, true);?>
            </select>
        <?php
	}
	
	function value($value) {
        $value = '<select name="' . $field_name . '" class="rating" disabled="disabled">
            <option value="">Choose</option>
            ' . html_options($rating_opts, $value, true) . '
        </select>';
                
		return $value;
	}
}
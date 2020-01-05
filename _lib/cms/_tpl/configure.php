<?php
if (1 != $auth->user['admin']) {
    die('access denied');
}

global $db_config, $auth_config, $upload_config, $shop_config, $shop_enabled, $from_email, $tpl_config, $live_site, $vars, $table_dropped, $count, $section, $table, $fields;

// internal tables
$default_tables = [
    'file_fields' => [
        'date' => 'date',
        'name' => 'text',
        'size' => 'text',
        'type' => 'text',
    ],
    
    'cms_privileges_fields' => [
        'user' => 'text',
        'section' => 'text',
        'access' => 'integer',
        'filter' => 'text',
    ],
    
    'cms_filters' => [
        'user' => 'text',
        'section' => 'text',
        'name' => 'text',
        'filter' => 'textarea',
    ],
    
    'cms_multiple_select_fields' => [
        'section' => 'text',
        'field' => 'text',
        'item' => 'integer',
        'value' => 'text',
    ],
    
    'cms logs' => [
        'user' => 'select',
        'section' => 'text',
        'item' => 'integer',
        'task' => 'text',
        'details' => 'text',
        'date' => 'timestamp',
        'id' => 'id',
    ]
];

//section templates
$section_templates = [
    'blank' => [],
    'pages' => [
        'heading' => 'text',
        'copy' => 'editor',
        'page name' => 'page-name',
        'page title' => 'text',
        'meta description' => 'textarea',
        'id' => 'id',
    ],
    'page' => [
        'heading' => 'text',
        'copy' => 'editor',
        'meta description' => 'textarea',
    ],
    'blog' => [
        'heading' => 'text',
        'copy' => 'editor',
        'tags' => 'textarea',
        'blog category' => 'checkboxes',
        'date' => 'timestamp',
        'page name' => 'page-name',
        'display' => 'checkbox',
        'id' => 'id',
    ],
    'blog categories' => [
        'category' => 'text',
        'page name' => 'page-name',
        'id' => 'id',
    ],
    'news' => [
        'heading' => 'text',
        'copy' => 'editor',
        'date' => 'timestamp',
        'meta description' => 'textarea',
        'page name' => 'page-name',
        'id' => 'id',
    ],
    'comments' => [
        'name' => 'text',
        'email' => 'email',
        'website' => 'url',
        'comment' => 'textarea',
        'blog' => 'select',
        'date' => 'timestamp',
        'ip' => 'text',
        'id' => 'id',
    ],
    'enquiries' => [
        'name' => 'text',
        'email' => 'email',
        'tel' => 'tel',
        'enquiry' => 'textarea',
        'date' => 'timestamp',
        'id' => 'id',
    ]
];

function array_to_csv($array): ?string // returns null or string
{
    if (null === $array) {
        return '';
    }

    foreach ($array as $k => $v) {
        $array[$k] = "\t'" . addslashes($v) . "'";
    }

    return implode(",\n", $array);
}

function str_to_csv($str): string
{
    if (!$str) {
        return '';
    }

    $array = explode("\n", $str);

    foreach ($array as $k => $v) {
        $array[$k] = "\t'" . addslashes(trim($v)) . "'" . '';
    }

    return implode(",\n", $array);
}

function str_to_assoc($str): string
{
    if (!$str) {
        return '';
    }

    $array = explode("\n", $str);

    foreach ($array as $k => $v) {
        $pos = strpos($v, '=');
        $array[$k] = "\t'" . addslashes(trim(substr($v, 0, $pos))) . "'=>'" . addslashes(trim(substr($v, $pos + 1))) . "'" . '';
    }

    return implode(",\n", $array);
}

function str_to_bool($str): string
{
    if ($str) {
        return 'true';
    }
    return 'false';
}

// generate field list from the components dir
foreach (glob("_lib/cms/components/*.php") as $filename) {
    $field_opts[] = str_replace('.php', '', basename($filename));
}

// get section list for subsections and dropdowns
$section_opts = array_keys($vars['fields']);

//check config file
global $root_folder;
$config_file = $root_folder . '/_inc/config.php';

if (!file_exists($config_file)) {
    die('Error: config file does not exist: ' . $config_file);
} elseif (!is_writable($config_file)) {
    die('Error: config file is not writable: ' . $config_file);
}

function loop_fields($field_arr)
{
    global $vars, $table_dropped, $count, $section, $table, $fields, $cms;

    foreach ($field_arr as $k => $v) {
        $count['fields']++;

        if (is_array($v)) {
            loop_fields($v);
        } else {
            if ($table_dropped) {
                continue;
            }

            $new_name = $_POST['vars']['fields'][$count['sections']][$count['fields']]['name'];
            $new_type = $_POST['vars']['fields'][$count['sections']][$count['fields']]['value'];

            // optimise select fields
            if ('select' == $new_type) {
                foreach ($_POST['options'] as $option) {
                    if ($option['name'] == $new_name) {
                        if ('section' == $option['type']) {
                            $new_type = 'integer';
                        }

                        break;
                    }
                }
            }

            $fields[] = $new_name;

            // drop fields
            if (!$_POST['vars']['fields'][$count['sections']][$count['fields']]) {
                if (in_array($v, ['id', 'separator', 'checkboxes'])) { //don't drop id!
                    continue;
                }

                $query = "ALTER TABLE `$table` DROP `" . underscored($k) . '`';
                sql_query($query);
                continue;
            }

            if (underscored($k) != underscored($new_name) or $v != $new_type) {
                $db_field = $cms->form_to_db($new_type);

                if ($db_field) {
                    $query = "ALTER TABLE `$table` CHANGE `" . underscored($k) . '` `' . underscored($new_name) . '` ' . $db_field . ' ';
                }

                if ($query and 'hidden' != $new_type) {
                    sql_query($query);
                }

                //convert select to checkboxes
                if ('select' == $v and 'checkboxes' == $new_type) {
                    $rows = sql_query("SELECT * FROM `$table`");

                    foreach ($rows as $row) {
                        sql_query("INSERT INTO cms_multiple_select SET
							section='" . $section . "',
							field='" . $new_name . "',
							item='" . escape($row['id']) . "',
							value='" . escape($row[$new_name]) . "'
						");
                    }
                }
            }
        }
    }
}

if ($_POST['save']) {
    //die('disabled');
    
    if (!$_POST['last']) {
        die('Error: form submission incomplete');
    }

    // check internal tables
    foreach ($default_tables as $name => $fields) {
        $this->check_table($name, $fields);
    }

    $count['sections'] = 0;
    $count['fields'] = 0;
    $count['subsections'] = 0;
    $count['options'] = 0;

    foreach ($vars['fields'] as $section => $fields) {
        $count['sections']++;

        $table = underscored($section);

        $table_dropped = false;
        if (!$_POST['sections'][$count['sections']]) {
            $query = 'DROP TABLE IF EXISTS `' . $table . '`';
            sql_query($query);

            $table_dropped = true;
        }

        $fields = [];

        loop_fields($vars['fields'][$section]);

        if ($table_dropped) {
            continue;
        }

        $after = '';
        foreach ($_POST['vars']['fields'][$count['sections']] as $field_id => $field) {
            if (in_array($field['value'], ['separator', 'checkboxes'])) {
                continue;
            }

            if (in_array($field['value'], ['select', 'radio'])) {
                $field_options[] = $field['name'];
            }

            if (in_array($field['name'], $fields)) {
                $after = underscored($field['name']);
            } else {
                $db_field = $this->form_to_db($field['value']);

                if (!$db_field) {
                    continue;
                }

                if ($after) {
                    $query = "ALTER TABLE `$table` ADD `" . underscored($field['name']) . '` ' . $db_field . " NOT NULL AFTER `$after`";
                } else {
                    $query = "ALTER TABLE `$table` ADD `" . underscored($field['name']) . '` ' . $db_field . ' NOT NULL AFTER `id`'; //FIRST
                }

                if ($query) {
                    sql_query($query);
                }
            }
        }

        //rename table
        if ($_POST['sections'][$count['sections']] != $section) {
            $table = underscored($section);
            $new_table = underscored($_POST['sections'][$count['sections']]);

            $query = 'RENAME TABLE `' . $table . '`  TO `' . $new_table . '`';
            sql_query($query);
        }
    }

    foreach ($_POST['sections'] as $section_id => $section) {
        $table = underscored($section);

        if ($section_id > $count['sections']) {
            $fields = [];

            foreach ($_POST['vars']['fields'][$section_id] as $field_id => $field) {
                $fields[$field['name']] = $field['value'];
            }

            if (count($fields)) {
                $this->check_table($table, $fields);
            }
        }
    }

    $display = [];
    foreach ($_POST['vars']['settings'] as $k => $v) {
        if ($v['display']) {
            $display[] = $_POST['sections'][$k];
        }
    }

    //hash passwords
    if (!$auth_config['hash_password'] and $_POST['auth_config']['hash_password']) {
        $users = sql_query('SELECT * FROM users');

        foreach ($users as $user) {
            $password = $auth->create_hash($user['password']);
            sql_query("UPDATE users SET
    			password = '" . escape($password) . "'
    			WHERE
    				id = '" . $user['id'] . "'
    		");
        }
    }

    $config = '<?php
# GENERAL SETTINGS
$db_config["host"] = "' . $db_config['host'] . '";
$db_config["user"] = "' . $db_config['user'] . '";
$db_config["pass"] = "' . $db_config['pass'] . '";
$db_config["name"] = "' . $db_config['name'] . '";

$db_config["dev_host"] = "' . $db_config['dev_host'] . '";
$db_config["dev_user"] = "' . $db_config['dev_user'] . '";
$db_config["dev_pass"] = "' . $db_config['dev_pass'] . '";
$db_config["dev_name"] = "' . $db_config['dev_name'] . '";

$live_site=' . str_to_bool($_POST['live_site']) . ';

#TPL

// multipage templates
$tpl_config["catchers"] = [' . str_to_csv($_POST['tpl_config']['catchers']) . '];

// 301 redirects
$tpl_config["redirects"] = [' . str_to_assoc($_POST['tpl_config']['redirects']) . '];

// enforce ssl
$tpl_config["ssl"] = ' . str_to_bool($_POST['tpl_config']['ssl']) . ';

#USER LOGIN
$auth_config = [];

// table where your users are stored
$auth_config["table"] = "' . $_POST['auth_config']['table'] . '";

// required fields when registering and updating
$auth_config["required"] = [
	' . array_to_csv($_POST['auth_config']['required']) . '
];

// automated emails will be sent from this address
$from_email = "' . $_POST['from_email'] . '";
$auth_config["from_email"] = $from_email;

// specify pages where users are redirected
$auth_config["login"] = "' . $_POST['auth_config']['login'] . '";
$auth_config["register_success"] = "' . $_POST['auth_config']['register_success'] . '";
$auth_config["forgot_success"] = "' . $_POST['auth_config']['forgot_success'] . '";

// hash passwords
$auth_config["hash_password"] = ' . str_to_bool($_POST['auth_config']['hash_password']) . ';

// email activation
$auth_config["email_activation"] = ' . str_to_bool($_POST['auth_config']['email_activation']) . ';

// use a secret term to encrypt cookies
$auth_config["secret_phrase"] = "' . $_POST['auth_config']['secret_phrase'] . '";

// for use with session and cookie vars
$auth_config["cookie_prefix"] = "' . $_POST['auth_config']['cookie_prefix'] . '";

// how long a cookie lasts with remember me
$auth_config["cookie_duration"] = "' . $_POST['auth_config']['cookie_duration'] . '";

// send registration notifications
$auth_config["registration_notification"] = ' . str_to_bool($_POST['auth_config']['registration_notification']) . ';

$auth_config["facebook_appId"] = "' . $_POST['auth_config']['facebook_appId'] . '";
$auth_config["facebook_secret"] = "' . $_POST['auth_config']['facebook_secret'] . '";

$auth_config["login_wherestr"] = "' . $_POST['auth_config']['login_wherestr'] . '";

#UPLOADS
$upload_config = [];

// configure the variables before use.
$upload_config["upload_dir"] = "' . $_POST['upload_config']['upload_dir'] . '";
$upload_config["resize_images"] = ' . str_to_bool($_POST['upload_config']['resize_images']) . ';
$upload_config["resize_dimensions"] = [' . str_replace('x', ',', $_POST['upload_config']['resize_dimensions']) . '];

$upload_config["allowed_exts"] = [' . str_to_csv($_POST['upload_config']['allowed_exts']) . '];

# ADMIN AREA
// sections in admin navigation
$vars["sections"] = [
' . array_to_csv($display) . '
];

// fields in each section
';

    foreach ($_POST['sections'] as $section_id => $section) {
        $fields = '';
        $required = '';
        $subsections = '';
        $label = '';

        foreach ($_POST['vars']['fields'][$section_id] as $field_id => $field) {
            if ($_POST['vars']['fields'][$section_id][$field_id]['label']) {
                $label .= '"' . $field['name'] . '" => "' . $_POST['vars']['fields'][$section_id][$field_id]['label'] . '", ';
            }

            $fields .= "\t" . '"' . $field['name'] . '"=>"' . $field['value'] . '",' . "\n";

            if ($_POST['vars']['required'][$field_id]) {
                $required .= '"' . $field['name'] . '",';
            }
        }

        foreach ($_POST['vars']['subsections'][$section_id] as $subsection) {
            $subsections .= '"' . $subsection . '",';
        }

        $config .= '
$vars["fields"]["' . $section . '"] = [
' . chop($fields) . '
];

$vars["required"]["' . $section . '"] = [' . $required . '];

$vars["label"]["'.$section.'"]=array('.$label.');

$vars["subsections"]["' . $section . '"] = [' . $subsections . '];

';
    }

    $config .= '

$vars["files"]["dir"] = "' . $_POST['vars']['files']['dir'] . '"; //folder to store files

# SHOP
$shop_enabled = ' . str_to_bool($_POST['shop_enabled']) . ';
$shop_config["paypal_email"] = "' . $_POST['shop_config']['paypal_email'] . '";
$shop_config["include_vat"] = ' . str_to_bool($_POST['shop_config']['include_vat']) . ';

# OPTIONS
';

    foreach ($_POST['options'] as $option) {
        while (in_array($option['name'], $field_options)) {
            $index = array_search($option['name'], $field_options);
            unset($field_options[$index]);
        }

        if ($option['section']) {
            $config .= '
$vars["options"]["' . $option['name'] . '"] = "' . $option['section'] . '";
';
        } else {
            $option['list'] = strip_tags($option['list']);

            if (strstr($option['list'], '=')) {
                $config .= '
$vars["options"]["' . $option['name'] . '"] = [
' . str_to_assoc($option['list']) . '
];
';
            } else {
                $config .= '
$vars["options"]["' . $option['name'] . '"] = [
' . str_to_csv($option['list']) . '
];
';
            }
        }
    }

    foreach ($field_options as $field_option) {
        if (!in_array($field_option, $_POST['options'])) {
            $config .= '
$vars["options"]["' . $field_option . '"] = "";
';
        }
    }

    //die($config);
    file_put_contents($config_file, $config);

    unset($_POST);

    $_SESSION['message'] = 'Configuration Saved';

    redirect('/admin?option=configure');
}

$count['sections'] = 0;
$count['fields'] = 0;
$count['subsections'] = 0;
$count['options'] = 0;
?>

<style>
    #dropdowns .list select {
        display: none;
    }
    #dropdowns .list textarea {
        display: block;
    }
    #dropdowns select {
        display: block;
    }
    #dropdowns textarea {
        display: none;
    }
</style>

<form method="post" id="form">
    <input type="hidden" name="save" value="1">
    
    <div id="tabs">
        <ul class="nav">
            <li><a href="#sections">Sections</a></li>
            <li><a href="#dropdowns">Dropdowns</a></li>
            <li><a href="#general">General</a></li>
            <li><a href="#template">Template</a></li>
            <li><a href="#login">Login</a></li>
            <li><a href="#upload">Upload</a></li>
        </ul>
    
        <div id="sections">
            <div class="container"></div>
            
            <p class="mt-3">
        		<select id="section_template">
        			<?=html_options(array_keys($section_templates));?>
        		</select>
        		<button class="addSection" type="button">Add section</button>
            </p>
    
        </div>
        
        <div id="dropdowns">
            
            <div class="container"></div>
            <span class="addDropdown">Add option</span>
            
        </div>
    
        <div id="general">
            <div id="general_config">
            	<div style="padding:5px 10px;">
            		<label>From email</label><br>
                    <input type="text" name="from_email" value="<?=$from_email;?>">
            		<br>
            		<br>
            		
            		<label>Folder to store upload data</label><br>
                    <input type="text" name="vars[files][dir]" value="<?=$vars['files']['dir'];?>">
            		<br>
            		<br>
            		
            		<label>Shopping cart</label><br>
                    <input type="checkbox" name="shop_enabled" value="1" <?php if ($shop_enabled) { ?> checked<?php } ?>>
            		<br>
            		<br>
            		
            		<label>Paypal email</label><br>
                    <input type="text" name="shop_config[paypal_email]" value="<?=$shop_config['paypal_email'];?>">
            		<br>
            		<br>
            		
            		<label>VAT</label><br>
                    <input type="checkbox" name="shop_config[include_vat]" value="1" <?php if ($shop_config['include_vat']) { ?> checked<?php } ?>>
            		<br>
            		<br>
            		
            	</div>
            </div>
        </div>
    
        <div id="template">
            <div id="tpl_config">
            	<div style="padding:5px 10px;">
            		<label>Catchers</label><br>
            		<textarea name="tpl_config[catchers]" class="autogrow" style="width: 100%;"><?=implode("\n", $tpl_config['catchers']);?></textarea>
            		<br>
            		<br>
            		
            		<label>Redirects</label><br>
    				<?php
                    $redirects = '';
                    foreach ($tpl_config['redirects'] as $k => $v) {
                        $redirects .= $k . '=' . $v . "\n";
                    }
                    $redirects = trim($redirects);
                    ?>
    				<textarea name="tpl_config[redirects]" class="autogrow" style="width: 100%;"><?=$redirects;?></textarea>
            		<br>
            		<br>
            		
        			<label>Site-wide SSL</label><br>
    	        	<input type="checkbox" name="tpl_config[ssl]" value="1" <?php if ($tpl_config['ssl']) { ?> checked<?php } ?>>
            	</div>
            </div>
        </div>
    
        <div id="login">
    		<label>Table</label><br>
    		<input type="text" name="auth_config[table]" value="<?=$auth_config['table'];?>">
    		<br>
    		<br>
    		
    		<label>Login</label><br>
            <input type="text" name="auth_config[login]" value="<?=$auth_config['login'];?>">
    		<br>
    		<br>
    		
    		<label>Register success</label><br>
            <input type="text" name="auth_config[register_success]" value="<?=$auth_config['register_success'];?>">
    		<br>
    		<br>
    		
    		<label>Forgot success</label><br>
            <input type="text" name="auth_config[forgot_success]" value="<?=$auth_config['forgot_success'];?>">
    		<br>
    		<br>
    		
    		<label>Hash passwords</label><br>
            <input type="checkbox" name="auth_config[hash_password]" value="1" <?php if ($auth_config['hash_password']) { ?> checked<?php } ?>>
    		<br>
    		<br>
    		
    		<label>Email activation</label><br>
            <input type="checkbox" name="auth_config[email_activation]" value="1" <?php if ($auth_config['email_activation']) { ?> checked<?php } ?>>
    		<br>
    		<br>
    		
    		<label>Secret phrase</label><br>
            <input type="text" name="auth_config[secret_phrase]" value="<?=$auth_config['secret_phrase'];?>">
    		<br>
    		<br>
    		
    		<label>Cookie prefix</label><br>
            <input type="text" name="auth_config[cookie_prefix]" value="<?=$auth_config['cookie_prefix'];?>">
    		<br>
    		<br>
    		
    		<label>Cookie duration</label><br>
            <input type="text" name="auth_config[cookie_duration]" value="<?=$auth_config['cookie_duration'];?>">
    		<br>
    		<br>
    		
    		<label>Registration notification</label><br>
            <input type="checkbox" name="auth_config[registration_notification]" value="1" <?php if ($auth_config['registration_notification']) { ?> checked<?php } ?>>
    		<br>
    		<br>
    		
    		<label>Facebook appId</label><br>
            <input type="text" name="auth_config[facebook_appId]" value="<?=$auth_config['facebook_appId'];?>">
    		<br>
    		<br>
    		
    		<label>Facebook secret</label><br>
            <input type="text" name="auth_config[facebook_secret]" value="<?=$auth_config['facebook_secret'];?>">
    		<br>
    		<br>
    		
    		<label>Login wherestr</label><br>
            <textarea name="auth_config[login_wherestr]"><?=$auth_config['login_wherestr'];?></textarea>
    		<br>
    		<br>
            		
        </div>
    
        <div id="upload">
            <label>Upload dir</label><br>
            <input type="text" name="upload_config[upload_dir]" value="<?=$upload_config['upload_dir'];?>">
    		<br>
    		<br>
            		
            <label>Resize images</label><br>
            <input type="checkbox" name="upload_config[resize_images]" value="1" <?php if ($upload_config['resize_images']) { ?> checked<?php } ?>>
    		<br>
    		<br>
            		
            <label>Resize dimensions</label><br>
            <input type="text" name="upload_config[resize_dimensions]" value="<?=implode('x', $upload_config['resize_dimensions']);?>">
    		<br>
    		<br>
            		
            <label>Allowed exts</label><br>
            <textarea type="text" name="upload_config[allowed_exts]" class="autogrow"><?=implode("\n", $upload_config['allowed_exts']);?></textarea>
    		<br>
    		<br>
    
        </div>
    </div>
    
    <br>
        <p>
    		<button type="submit" onclick="return confirm('WARNING: changing settings can result in loss of data or functionality. Are you sure you want to continue?');">Save config</button>
    	</p>
    </div>
    <br>
    
    <input type="hidden" name="last" value="1">
</form>


<template id="sectionTemplate">
    
   <div class="row mb-3 draggableSections">
        <div class="col-sm-1">
            <div class="handle" style="height:100%;"><i class="fas fa-square"></i></div>
    		<span class="toggle_section"><i class="fas fa-caret-right"></i></span>
        </div>
        <div class="col-sm-11">
    		<p>
    			<input class="name" type="text" name="sections[{$count}]" value="">
    			<span class="del_row"><i class="fas fa-trash"></i></span>
    		</p>
    
    		<div class="settings" style="display:none;">
    			<label>
    				<input class="display" type="checkbox" name="vars[settings][{$count}][display]" value="1" checked="checked">  Show in navigation
    			</label>
    			<br>
    			
    			<div class="fields">
    		    	<div class="container"></div>
    			    <span class="addField" data-section_id="{$count}">Add field</span>
    			</div>
    			<br>
    
    			<h4>Subsections</h4>
    			<div class="subsections">
    		    	<div class="container"></div>
    			    <span class="addSubsection" data-section_id="{$count}">Add subsection</span>
    			</div>
    		</div>
        </div>
    </div>

</template>

<template id="fieldTemplate">

   <div class="row mb-3 draggable">
        <div class="col-sm-1">
			<div class="handle"><i class="fas fa-square"></i></div>
        </div>
        <div class="col-sm-3">
			<input class="name" type="text" name="vars[fields][{$section_id}][{$count}][name]" value="">
        </div>
        <div class="col-sm-3">
			<input class="label" type="text" name="vars[fields][{$section_id}][{$count}][label]" value="">
        </div>
        <div class="col-sm-3">
    		<select class="field" name="vars[fields][{$section_id}][{$count}][value]">
    			<?=html_options($field_opts);?>
    		</select>
        </div>
        <div class="col-sm-1">
            <input class="required" type="checkbox" name="vars[required][{$count}]" value="1">
        </div>
        <div class="col-sm-1">
            <span class="del_row"><i class="fas fa-trash"></i></span>
        </div>
    </div>
    
</template>

<template id="subsectionTemplate">
    
   <div class="row mb-3 draggable">
        <div class="col-sm-1">
			<div class="handle"><i class="fas fa-square"></i></div>
        </div>
        <div class="col-sm-10">
    		<select name="vars[subsections][{$section_id}][]">
    			<?=html_options($section_opts, $v);?>
    		</select>
        </div>
        <div class="col-sm-1">
            <span class="del_row"><i class="fas fa-trash"></i></span>
        </div>
    </div>
    
</template>

<template id="dropdownTemplate">
    
   <div class="row mb-3 list">
        <div class="col-sm-5">
			<input class="name" type="text" name="options[{$count}][name]">
        </div>
        <div class="col-sm-5">
			<textarea cols="30" type="text" name="options[{$count}][list]" class="autogrow"></textarea>
			<select name="options[{$count}][section]" disabled>
			    <?=html_options($section_opts, $val);?>
			</select>
        </div>
        <div class="col-sm-1">
			<span class="toggle_list_type"> <i class="fas fa-list"></i></span>
        </div>
        <div class="col-sm-1">
            <span class="del_row"><i class="fas fa-trash"></i></span>
        </div>
    </div>
    
</template>

<script>
    var section_templates=<?=json_encode($section_templates);?>;
    var count = <?=json_encode($count);?>;
    var vars = <?=json_encode($vars);?>;
    var max_input_vars = '<?=ini_get('max_input_vars');?>';

    console.log(vars);
    
    function initSortables(){
        $( ".fields, .subsections" ).sortable({
            handle: '.handle',
            opacity: 0.5,
            items: ".draggable",
            axis: 'y'
        });
    
        $( "#sections" ).sortable({
            handle: '.handle',
            opacity: 0.5,
            items: ".draggableSections",
            axis: 'y'
        });
    }
    
    $(function () {
        $( "#tabs" ).tabs();
            
        // populate sections
        Object.entries(vars.fields).forEach(entry => {
            let key = entry[0];
            let value = entry[1];
            
            count.sections++;
            var html = $('#sectionTemplate').html()
                .split('{$count}').join(count.sections);
                
    		var row = $(html).appendTo($('#sections>.container'));
    		
    		// display
    		if (vars.sections.indexOf(key) == -1) {
    		    row.find('.display').prop('checked', false);
    		}
    		
    		row.find('.name').val(key);
    		
    		// fields
    		Object.entries(value).forEach(item => {
                let name = item[0];
                let type = item[1];
            
                count.fields++;
                var html = $('#fieldTemplate').html()
                    .split('{$count}').join(count.fields)
                    .split('{$section_id}').join(count.sections);
                    
    		    var fieldRow = $(html).appendTo(row.find('.fields>.container'));
    		    fieldRow.find('.name').val(name);
    		    
    		    if (vars.label) {
    		        fieldRow.find('.label').val(vars.label[name]);
    		    }
    		    
    		    if (vars.required[key].indexOf(name) != -1 ) {
    		        fieldRow.find('.required').prop('checked', true);
    		    }
    		    
    		    fieldRow.find('select').val(type.replace('-', '_'));
    		});
    		
    		// subsections
    		vars.subsections[key].forEach(function(item) {
                count.subsections++;
                var html = $('#subsectionTemplate').html()
                    .split('{$count}').join(count.subsections)
                    .split('{$section_id}').join(count.sections);
                    
    		    var subsectionRow = $(html).appendTo(row.find('.subsections>.container'));
    		    subsectionRow.find('select').val(item);
    		});
        });
        
        // populate dropdowns
        Object.entries(vars.options).forEach(entry => {
            let key = entry[0];
            let value = entry[1];
            
            count.options++;
            var html = $('#dropdownTemplate').html()
                .split('{$count}').join(count.options);
    		var row = $(html).appendTo($('#dropdowns .container'));
    		
    		if (typeof value == "string") {
    		    row.removeClass('list').find('select').val(value).prop('disabled', false);
    	    	row.find('textarea').prop('disabled', true);
    		} else {
    		    val = '';
    		    if (Array.isArray(value)) {
        		    val = '';
        		    value.forEach(function(item) {
        		        val += item + "\n";
        		    })
        		} else {
        		    Object.entries(value).forEach(item => {
        		        val += item[0] + '=' + item[1] + "\n";
        		    });
        		}
        		
    	    	row.find('textarea').val(val.trim());
    		}
    		
    		row.find('.name').val(key);
        });
    
        initSortables();
    });
    
    $('body').on('click', '.addSection', function() {
        count.sections++;
        var html = $('#sectionTemplate').html()
            .split('{$count}').join(count.sections);
                
    	var row = $(html).appendTo($('#sections>.container'));
    	var section_template = $('#section_template').val();
    	row.find('input').val(section_template).first().focus().select();
    	
    	if (section_templates[section_template]) {
    	    
            Object.entries(section_templates[section_template]).forEach(entry => {
                let name = entry[0];
                let type = entry[1];
            
                count.fields++;
                var html = $('#fieldTemplate').html()
                    .split('{$count}').join(count.fields)
                    .split('{$section_id}').join(count.sections);
    		    var fieldRow = $(html).appendTo(row.find('.fields>.container'));
    		    fieldRow.find('.name').val(name);
    		    fieldRow.find('select').val(type.replace('-', '_'));
            });
            
    	}
    	
        initSortables();
    });
    
    $('body').on('click', '.toggle_list_type', function () {
        var row = $(this).closest('.row');

        if (row.hasClass('list')) {
            row.find('select').prop('disabled', false).show();
            row.find('textarea').prop('disabled', true).hide();
            row.removeClass('list');
        } else {
            row.find('select').prop('disabled', true).hide();
            row.find('textarea').prop('disabled', false).show();
            row.addClass('list');
        }
    })
    
    $('body').on('click', '.del_row', function () {
        $(this).closest('.row').remove();
    });

    $('body').on('click', '.addField', function() {
        count.fields++;
        var html = $('#fieldTemplate').html()
            .split('{$count}').join(count.fields)
            .split('{$section_id}').join($(this).data('section_id'));
            
    	var row = $(html).appendTo($(this).parent().find('.container'));
    	row.find('input').first().focus();
    });

    $('body').on('click', '.addSubsection', function() {
        count.subsections++;
        var html = $('#subsectionTemplate').html()
            .split('{$count}').join(count.subsections)
            .split('{$section_id}').join($(this).data('section_id'));
        
    	var row = $(html).appendTo($(this).parent().find('.container'));
    	row.find('select').first().focus();
    });

    $('body').on('click', '.addDropdown', function() {
        count.options++;
        var html = $('#dropdownTemplate').html()
            .split('{$count}').join(count.options);
    	var row = $(html).appendTo($('#dropdowns .container'));
    	row.find('input').focus();
    });
    
    $('body').on('click', '.toggle_section', function() {
        $(this).find('i').toggleClass('fa-rotate-90');
        $(this).closest('.row').find('.settings').slideToggle();
    });
    
    $('body').on('blur', '.field', function() {
        var field = $(this).closest('.row').find('.name');
        
        if(field.val()=='') { 
            field.val($(this).val().replace('-',' ')) 
        }
    })
    
    // check field count doesn't exceed phps max allowed input setting
    $('form[method*=post]').on('submit', function(e) {
        if(post_count(this) > max_input_vars) {
            e.preventDefault();
            alert('Save aborted: This form has too many fields for the server to accept.');
        }
    })
</script>

<script>
	/**
	 * Count the number of fields that will be posted in a form.
	 */
	function post_count(formEl) {
		// These will count as one post parameter each.
		var fields = $('textarea:enabled[name]', formEl).toArray();

		// Find the basic textual input fields (text, email, number, date and similar).
		fields = fields.concat(
			$('input:enabled[name]', formEl)
				// Data items that are handled later.
				.not("[type='checkbox']:not(:checked)")
				.not("[type='radio']")
				.not("[type='file']")
				.not("[type='reset']")
				// Submit form items.
				.not("[type='submit']")
				.not("[type='button']")
				.not("[type='image']")
				.toArray()
		);

		// Single-select lists will always post one value.
		fields = fields.concat(
			$('select:enabled[name]', formEl)
				.not('[multiple]')
				.toArray()
		);

		// Multi-select lists will post one parameter for each selected option.
		$('select[multiple]:enabled[name] option:selected', formEl).each(function() {
			// We collect all the options that have been selected.
			fields = fields.concat(formEl);
		});

		// Each radio button group will post one parameter.
		fields = fields.concat(
			$('input:enabled:radio:checked', formEl)
				.toArray()
		);

		return fields.length;
	};
</script>

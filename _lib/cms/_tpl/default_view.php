<?php
// check permissions
if (1 != $auth->user['admin'] and !$auth->user['privileges'][$this->section]) {
    die('access denied');
}

$this->set_section($this->section, $_GET['id']);
$content = $this->content;

// get id if it exists
$id = $content['id'];

// mark as read
if ('0' === $content['read']) {
    $content['read'] = 1;
    $this->save($content);
}

$has_logs = table_exists('cms_logs');

$has_priveleges = false;
if (
    1 == $auth->user['admin'] and
    underscored($this->section) == $auth->table and
    (2 == $content['admin'] or 3 == $content['admin'])
) {
    $has_priveleges = true;
}

if ($_POST['custom_button']) {
    $cms_buttons[$_POST['custom_button']]['handler']($_GET['id']);

    $content = $this->get($this->section, $_GET['id']);
}

// get all items
if ($_POST['select_all_pages']) {
    $conditions = [];
    foreach ($vars['fields'][$_POST['section']] as $k => $v) {
        if (('select' == $v or 'combo' == $v or 'radio' == $v) and $vars['options'][$k] == $_GET['option']) {
            $conditions[$k] = escape($this->id);
            break;
        }
    }

    $items = $this->get($_POST['section'], $conditions);
    
    $_POST['items'] = [];
    foreach ($items as $v) {
        $_POST['items'][] = $v['id'];
    }
}

//bulk export
if ('export' == $_POST['action']) {
    $this->export_items($_POST['section'], $_POST['id']);
}

//subsections delete
if ('delete' == $_POST['action']) {
    $this->delete_items($_POST['section'], $_POST['id']);
}

//save privileges
if (
    1 == $auth->user['admin'] and
    underscored($this->section) == $auth->table and
    (2 == $content['admin'] or 3 == $content['admin']) and
    $_POST['privileges']
) {
    sql_query("DELETE FROM cms_privileges WHERE user='" . $this->id . "'");

    foreach ($_POST['privileges'] as $k => $v) {
        sql_query("INSERT INTO cms_privileges SET
			user='" . escape($this->id) . "',
			section='" . escape($k) . "',
			access='" . escape($v) . "',
			filter='" . escape($_POST['filters'][$k]) . "'
		");
    }
}

if ($languages) {
    $languages = array_merge(['en'], $languages);
} else {
    $languages = ['en'];
}

if ($_POST['delete'] and $this->id) {
    $this->delete_items($this->section, $this->id);

    redirect('?option=' . $this->section);
}

//label
$label = $this->get_label();

$title = ucfirst($this->section) . ' | ' . ($label ? $label : '&lt;blank&gt;');

// previous / next links
if (isset($content['position'])) {
    $conditions = $_GET;
    unset($conditions['id']);

    $qs = http_build_query($conditions);

    $conditions['position'] = $content['position'];
    $conditions['func']['position'] = '<';

    $prev = $this->get($this->section, $conditions, 1, null, false);

    $conditions['func']['position'] = '>';

    $next = $this->get($this->section, $conditions, 1);

    //var_dump($prev); exit;

    if ($prev) {
        $prev_link = '?id=' . $prev['id'] . '&' . $qs;
    }

    if ($next) {
        $next_link = '?id=' . $next['id'] . '&' . $qs;
    }
}


$qs_arr = $_GET;
unset($qs_arr['option']);
unset($qs_arr['view']);
unset($qs_arr['id']);
$qs = http_build_query($qs_arr);

$section = '';
foreach ($vars['fields'][$this->section] as $name => $type) {
    if ($_GET[underscored($name)] and 'id' != $name and 'select' == $type) {
        $section = $name;
        break;
    }
}

if ($section and in_array('id', $vars['fields'][$this->section])) {
    $back_link = '?option=' . $vars['options'][$section] . '&view=true&id=' . $this->content[$section];
    $back_label = ucfirst($vars['options'][$section]);
} else {
    $back_link = '?option=' . $this->section . '&' . http_build_query($_GET['s']);
    $back_label = ucfirst($this->section);
} ?>

<!-- page title area start -->
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-10">
            <div class="breadcrumbs-area clearfix" style="margin: 20px 0;">
                <h4 class="page-title pull-left">Admin</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="/admin">Dashboard</a></li>
                    <li><a href="<?=$back_link; ?>"><?=$back_label; ?></a></li>
                    <li><?=$this->get_label(); ?></li>
                </ul>
            </div>
        </div>

        <div class="col-sm-2 clearfix">
            <ul class="breadcrumbs pull-right">
		        <?php if ($prev_link) { ?>
		        <li><a href="<?=$prev_link;?>">Prev</a></li>
		        <?php } ?>
		        <?php if ($next_link) { ?>
		        <li><a href="<?=$next_link;?>">Next</a></li>
		        <?php } ?>
        	</ul>
        </div>

    </div>
</div>
<!-- page title area end -->


<div class="main-content-inner">
    <div class="row">



<!-- tab start -->
<div class="col-lg-12 mt-5">
    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="pills-summary-tab" data-toggle="pill" href="#pills-summary" role="tab" aria-controls="pills-summary" aria-selected="true">
                    	Summary
                    </a>
                </li>
				<?php
                foreach ($vars['subsections'][$this->section] as $count => $subsection) {
                    ?>
                <li class="nav-item">
                    <a class="nav-link" id="pills-<?=$count; ?>-tab" data-toggle="pill" href="#pills-<?=$count; ?>" role="tab" aria-controls="pills-tab_<?=$count; ?>" aria-selected="true">
						<?=ucfirst($subsection); ?>
					</a>
				</li>
				<?php
                } ?>
				<?php if ($has_priveleges) { ?>
                <li class="nav-item">
                    <a class="nav-link" id="pills-priveleges-tab" data-toggle="pill" href="#pills-priveleges" role="tab" aria-controls="pills-priveleges" aria-selected="true">
						Priveleges
					</a>
				</li>
				<?php } ?>
				<?php if ($has_logs) { ?>
                <li class="nav-item">
                    <a class="nav-link" id="pills-logs-tab" data-toggle="pill" href="#pills-logs" role="tab" aria-controls="pills-logs" aria-selected="true">
						Logs
					</a>
				</li>
				<?php } ?>
            </ul>
            <div class="tab-content mt-3" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-summary" role="tabpanel" aria-labelledby="pills-summary-tab">

                	<div>
				        <button class="btn btn-default" type="button" onclick="location.href='?option=<?=$this->section; ?>&edit=true&id=<?=$id; ?>&<?=$qs; ?>'" style="font-weight:bold;">Edit</button>

						<?php
                        foreach ($cms_buttons as $k => $button) {
                            if ($this->section == $button['section'] and 'view' == $button['page']) {
                                require('includes/button.php');
                            }
                        } ?>

						&nbsp;

				        <form method="post" style="display:inline;">
					        <input type="hidden" name="delete" value="1">
					        <button class="btn btn-danger" type="submit" onclick="return confirm('are you sure you want to delete?');">Delete</button>
				        </form>
                	</div>




<?php
foreach ($languages as $language) {
                            if ('en' !== $language and in_array('language', $vars['fields'][$this->section])) {
                                $this->language = $language;
                                $content = $this->get($this->section, $_GET['id']);
                            } ?>
	<div id="language_<?=$language; ?>" <?php if ('en' != $language) { ?>style="display:none;"<?php } ?> class="box">
	<table border="0" cellspacing="0" cellpadding="5" width="100%">
	<?php
    foreach ($vars['fields'][$this->section] as $name => $type) {
        $label = $vars['label'][$this->section][$name];

        if (!$label) {
            $label = ucfirst(str_replace('_', ' ', $name));
        }

        $value = $content[$name];
        $name = $name;

        if ('select-multiple' == $type or 'checkboxes' == $type) {
            if (!is_array($vars['options'][$name]) and $vars['options'][$name]) {
                $join_id = array_search('id', $vars['fields'][$vars['options'][$name]]);

                if (!$join_id) {
                    trigger_error('No Join id', E_USER_ERROR);
                }

                $rows = sql_query('SELECT `' . underscored(key($vars['fields'][$vars['options'][$name]])) . '`,T1.value FROM cms_multiple_select T1
					INNER JOIN `' . escape(underscored($vars['options'][$name])) . "` T2 ON T1.value = T2.$join_id
					WHERE
						T1.field='" . escape($name) . "' AND
						T1.item='" . $id . "' AND
						T1.section='" . $this->section . "'
					GROUP BY T1.value
					ORDER BY T2." . underscored(key($vars['fields'][$vars['options'][$name]])) . '
				');

                $value = '';
                foreach ($rows as $row) {
                    $value .= '<a href="?option=' . escape($vars['options'][$name]) . '&view=true&id=' . $row['value'] . '">' . current($row) . '</a><br>' . "\n";
                }
            } else {
                $rows = sql_query("SELECT value FROM cms_multiple_select
					WHERE
						field='" . escape($name) . "' AND
						item='" . $id . "'
					ORDER BY id
				");

                $value = '';
                foreach ($rows as $row) {
                    if (is_assoc_array($vars['options'][$name])) {
                        $value .= $vars['options'][$name][$row['value']] . '<br>' . "\n";
                    } else {
                        $value .= current($row) . '<br>' . "\n";
                    }
                }
            }
        }

        if (('' == $value or '0000-00-00' == $value or '00:00:00' == $value or 'password' == $type) and 'separator' != $type and !is_array($type)) {
            continue;
        }

        if (in_array($type, ['position','language','translated-from'])) {
            continue;
        }

        if ('id' == $type and !$vars['settings'][$this->section]['show_id']) {
            continue;
        }

        if ('separator' == $type) {
            ?>
		<tr>
			<td colspan="2">
				<br />
				<h2><?=ucfirst($name); ?></h2>
			</td>
		</tr>
		<?php
        } else {
            ?>

		<tr>
			<th align="left" valign="top" width="20%"><?=$label; ?></th>
			<td>
		<?php
        if (in_array($type, ['text','float','decimal','int','id','page-name','hidden','ip','deleted'])) {
            if (is_numeric($value) and 0 == $value) {
                continue;
            } ?>
			<?=$value; ?>
		<?php
        } elseif (is_array($type)) { ?>
			<?=$value;?>
		<?php } elseif ('mobile' == $type || 'tel' == $type) { ?>
			<?=$value;?>
		<?php } elseif ('url' == $type) { ?>
			<a href="<?=$value;?>" target="_blank"><?=$value;?></a>
		<?php } elseif ('email' == $type) { ?>
			<a href="mailto:<?=$value;?>" target="_blank"><?=$value;?></a>
		<?php } elseif ('postcode' == $type) { ?>
			<?=$value;?>
			<a href="http://maps.google.co.uk/maps?f=q&source=s_q&hl=en&geocode=&q=<?=$value;?>" target="_blank">(view map)</a>
		<?php } elseif ('coords' == $type) { ?>
			<?=htmlspecialchars(substr($value, 6, -1));?>
		<?php } elseif ('textarea' == $type) { ?>
			<?=nl2br(strip_tags($value));?>
		<?php } elseif ('editor' == $type) { ?>
			<?=$value;?>
		<?php
        } elseif ('file' == $type) {
            if ($value) {
                $file = sql_query("SELECT * FROM files WHERE id='" . escape($value) . "'");
            } ?>
				<a href="/_lib/cms/file.php?f=<?=$file[0]['id']; ?>" target="_blank">
				<?php
                $image_types = ['jpg','jpeg','gif','png'];
            if (in_array(file_ext($file[0]['name']), $image_types)) {
                ?>
					<img src="/_lib/cms/file_preview.php?f=<?=$file[0]['id']; ?>&w=400&h=400" id="<?=$name; ?>_thumb" /><br />
				<?php
            } ?>
				<?=$file[0]['name']; ?>
				</a> 

				<span style="font-size:9px;"><?=file_size($file[0]['size']); ?></span>

				<?php
                $doc_types = ['pdf','doc','docx','xls','tiff'];
            if (in_array(file_ext($file[0]['name']), $doc_types)) {
                ?>
				<a href="http://docs.google.com/viewer?url=<?=rawurlencode('http://' . $_SERVER['HTTP_HOST'] . '/_lib/cms/file.php?f=' . $file[0]['id'] . '&auth_user=' . $_SESSION[$auth->cookie_prefix . '_email'] . '&auth_pw=' . md5($auth->secret_phrase . $_SESSION[$auth->cookie_prefix . '_password'])); ?>" target="_blank">(view)</a>
				<?php
            } ?>
		<?php
        } elseif ('phpupload' == $type) { ?>
            <input type="text" name="<?=$name;?>" class="upload" value="<?=$value;?>" readonly="true">
		<?php
        } elseif (in_array($type, ['select', 'combo', 'radio'])) {
            if (!is_array($vars['options'][$name])) {
                if ('0' == $value) {
                    $value = '';
                } else {
                    $value = '<a href="?option=' . escape($vars['options'][$name]) . '&view=true&id=' . $value . '">' . $content[underscored($name) . '_label'] . '</a>';
                }
            } else {
                if (is_assoc_array($vars['options'][$name])) {
                    $value = $vars['options'][$name][$value];
                }
            } ?>
			<?=$value; ?>
		<?php
        } elseif ('parent' == $type) {
            reset($vars['fields'][$this->section]);

            $field = key($vars['fields'][$this->section]);

            $row = sql_query("SELECT id,`$field` FROM `" . $this->table . "` WHERE id='" . escape($value) . "' ORDER BY `" . underscored($label) . '`');

            $value = '<a href="?option=' . escape($this->section) . '&view=true&id=' . $value . '">' . ($row[0][$field]) . '</a>'; ?>
			<?=$value; ?>
		<?php
        } elseif ('select-multiple' == $type or 'checkboxes' == $type) {
            ?>
			<?=$value; ?>
		<?php
        } elseif ('checkbox' == $type) { ?>
			<?=$value ? 'Yes' : 'No' ;?>
		<?php } elseif ('files' == $type) {
            if ($value) {
                $value = explode("\n", $value);
            } ?>
				<ul id="<?=$name; ?>_files" class="files">
				<?php
                $count = 0;

            if (is_array($value)) {
                foreach ($value as $key => $val) {
                    $count++;

                    if ($value) {
                        $file = sql_query("SELECT * FROM files WHERE id='" . escape($val) . "'");
                    } ?>
				<img src="/_lib/cms/file_preview.php?f=<?=$file[0]['id']; ?>&w=320&h=240" id="<?=$name; ?>_thumb" /><br />
				<a href="/_lib/cms/file.php?f=<?=$file[0]['id']; ?>"><?=$file[0]['name']; ?></a><br />
				<br />
				<?php
                }
            } ?>
				</ul>
		<?php
        } elseif ('phpuploads' == $type) { ?>
            <textarea name="<?=$field_name;?>" class="upload" readonly="true"><?=$value;?></textarea>
		<?php
        } elseif ('color' == $type) {
            ?>
			<input type="color" value="<?=$value; ?>" disabled >
		<?php
        } elseif ('date' == $type) {
            if ('0000-00-00' != $value and '' != $value) {
                $value = dateformat('d/m/Y', $value);
            } ?>
			<?=$value; ?>
		<?php
        } elseif ('month' == $type) {
            if ('0000-00-00' != $value and '' != $value) {
                $value = dateformat('F Y', $value);
            } ?>
			<?=$value; ?>
		<?php
        } elseif ('dob' == $type) {
            if ('0000-00-00' != $value and '' != $value) {
                $age = age($value);
                $value = dateformat('d/m/Y', $value);
            } ?>
			<?=$value; ?> (<?=$age; ?>)
		<?php
        } elseif ('time' == $type) {
            ?>
			<?=$value; ?>
		<?php
        } elseif ('datetime' == $type) {
            if ('0000-00-00 00:00:00' != $value) {
                $date = explode(' ', $value);
                $value = dateformat('d/m/Y', $date[0]) . ' ' . $date[1];
            } ?>
			<?=$value; ?>
		<?php
        } elseif ('timestamp' == $type) {
            if ('0000-00-00 00:00:00' != $value) {
                $date = explode(' ', $value);
                $value = dateformat('d/m/Y', $date[0]) . ' ' . $date[1];
            } ?>
			<?=$value; ?>
		<?php
        } elseif ('rating' == $type) {
            $opts['rating'] = [
                    1 => 'Very Poor',
                    2 => 'Poor',
                    3 => 'Average',
                    4 => 'Good',
                    5 => 'Excellent',
                ]; ?>
			<select name="<?=$field_name; ?>" class="rating" disabled="disabled">
				<option value=""></option>
				<?=html_options($opts['rating'], $value); ?>
			</select>
		<?php
        } elseif ('number' == $type) { ?>
			<?=number_format($value, 2);?>
		<?php } ?>

			</td>
		</tr>
		<?php
        } ?>
	<?php
    } ?>
	</table>
	</div>
<?php
                        } ?>




                </div>

<?php
    foreach ($vars['subsections'][$this->section] as $count => $subsection) {
        if (count($vars['fields'][$subsection])) {
            $this->section = $subsection;

            $table = underscored($this->section);

            if (!count($vars['labels'][$this->section])) {
                reset($vars['fields'][$this->section]);
                $vars['labels'][$this->section][] = key($vars['fields'][$this->section]);
            }

            if (in_array('position', $vars['fields'][$this->section])) {
                //$order = 'position';
                $limit = null;
            } else {
                $label = $vars['labels'][$this->section][0];

                $type = array_search($label, $vars['fields'][$this->section]);
                if (('select' == $typw or 'combo' == $typw) and !is_array($vars['opts'][$label])) {
                    //$order='T_'.$label.'.'.underscored(key($vars['fields'][$vars['options'][$label]]));
                }
                //$order="T_$table.".underscored($vars['labels'][$this->section][0]);

                $limit = 10;
            }

            $conditions = [];
            $qs = [];

            foreach ($vars['fields'][$this->section] as $k => $v) {
                if (('select' == $v or 'combo' == $v or 'radio' == $v) and $vars['options'][$k] == $_GET['option']) {
                    $conditions[$k] = escape($this->id);

                    $qs[$k] = $this->id;

                    break;
                }
            }

            $qs = http_build_query($qs);

            $first_field_type = $vars['fields'][$this->section][$vars['labels'][$this->section][0]];
            $asc = ('date' == $first_field_type or 'timestamp' == $first_field_type) ? false : true;

            $order = null;
            $vars['content'] = $this->get($subsection, $conditions, $limit, $order, $asc, $table);
            $p = $this->p;
        } ?>

<div class="tab-pane fade" id="pills-<?=$count; ?>" role="tabpanel" aria-labelledby="pills-<?=$count; ?>-tab">
	<?php
    if (count($vars['fields'][$subsection])) {
        require(dirname(__FILE__) . '/list.php');
    } elseif (file_exists('_tpl/admin/' . $subsection . '.php')) {
        require('_tpl/admin/' . $subsection . '.php');
    } ?>
</div>


<script>
	$('#tab_<?=$count; ?>').text('<?=ucfirst($subsection); ?> (<?=$p->total; ?>)');
</script>

<?php
    } ?>

<?php if ($has_priveleges) { ?>
	<div class="tab-pane fade" id="pills-priveleges" role="tabpanel" aria-labelledby="pills-priveleges-tab">
	    <div class="box" style="clear:both;">
		<?php
        //privileges
        if (
            1 == $auth->user['admin'] and
            underscored($_GET['option']) == $auth->table and
            (2 == $content['admin'] or 3 == $content['admin'])
         ) {
            check_table('cms_privileges', $this->cms_privileges_fields);

            $rows = sql_query("SELECT * FROM cms_privileges WHERE user='" . $this->id . "'");
            foreach ($rows as $row) {
                $privileges[$row['section']] = $row;
            } ?>
		<form method="post">
		<table class="box" width="100%">
		<tr>
			<th>Section</th>
			<th>
				Access<br>
				<a href="#" id="privileges_none">None</a>,
				<a href="#" id="privileges_read">Read</a>,
				<a href="#" id="privileges_write">Write</a>
			</th>
			<th>Filter</th>
		</tr>
		<?php
            foreach ($vars['fields'] as $section => $fields) {
                ?>
		<tr>
			<td><?=$section; ?></td>
			<td>
				<select name="privileges[<?=$section; ?>]" class="privileges">
					<option value=""></option>
					<?=html_options([1 => 'Read',2 => 'Write'], $privileges[$section]['access']); ?>
				</select>
			</td>
			<td>
				<input type="text" id="filters_<?=underscored($section); ?>" name="filters[<?=$section; ?>]" value="<?=$privileges[$section]['filter']; ?>" />
				<button type="button" onclick="choose_filter('<?=$section; ?>');">Choose Filter</button>
			</td>
		</tr>
		<?php
            } ?>
		<tr>
			<td>Email Templates</td>
			<td>
				<select name="privileges[email_templates]" class="privileges">
					<option value=""></option>
					<?=html_options([1 => 'Read',2 => 'Write'], $privileges['uploads']['access']); ?>
				</select>
			</td>
		</tr>
		<tr>
			<td>Uploads</td>
			<td>
				<select name="privileges[uploads]" class="privileges">
					<option value=""></option>
					<?=html_options([1 => 'Read',2 => 'Write'], $privileges['uploads']['access']); ?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="right">
				<button type="submit">Save</button>
			</td>
		</tr>
		</table>
		</form>
		<br />
		<?php
        }
        ?>
		</div>
	</div>
<?php } ?>

<?php if ($has_logs) { ?>
	<div class="tab-pane fade" id="pills-logs" role="tabpanel" aria-labelledby="pills-logs-tab">
	    <div class="box" style="clear:both;">
			<?php
                /*
                if( $_GET['option']=='users' ){
                    $query = "SELECT *,L.date FROM cms_logs L
                        INNER JOIN users U ON L.user=U.id
                        WHERE
                            user='".escape($_GET['id'])."'
                        ORDER BY L.date DESC
                    ";
                }else{
                */
                    $query = "SELECT *,L.date FROM cms_logs L
						LEFT JOIN users U ON L.user=U.id
						WHERE
							section='" . escape($_GET['option']) . "' AND
							item='" . escape($id) . "'
						ORDER BY L.id DESC
					";
                //}

                $p = new paging($query, 20);

                $logs = sql_query($p->query);

                if (count($logs)) {
                    ?>
			<div style="overflow: scroll; background: #fff;">
			<?php
                foreach ($logs as $k => $v) {
                    if ('users' == $_GET['option']) {
                        $item_table = underscored($v['section']);

                        if ($vars['fields'][$v['section']]) {
                            $item = sql_query("SELECT * FROM `$item_table` WHERE id='" . escape($v['item']) . "'", 1);
                            $label = key($vars['fields'][$v['section']]);
                            $item_name = $item[$label];
                        }
                    }

                    $name = $v['name'] ? $v['name'] . ' ' . $v['surname'] : $v['email']; ?>
			<p>
				<strong><a href="?option=<?=$v['section']; ?>&view=true&id=<?=$v['item']; ?>"><?=$item_name; ?></a> <?=ucfirst($v['task']); ?> by <a href="?option=users&view=true&id=<?=$v['user']; ?>"><?=$name; ?></a> on <?=$v['date']; ?></strong><br>
				<?=nl2br($v['details']); ?>
			</p>
			<br>
			<br>
			<?php
                } ?>
			<p>
				<?=$p->get_paging(); ?>
			</p>
			</div>

			<br />
			<br />
			<?php
                }
            ?>
		</div>
	</div>
<?php } ?>
            </div>
        </div>
    </div>
</div>
<!-- tab end -->

	</div>
</div>



<script>
function init()
{
	if( jQuery('#language') ){
		jQuery('#language').on('change', set_language);
	}

	init_tabs();
}

function set_language()
{
	var option=document.getElementById('language');

	for( j=0; j<option.options.length; j++ ){;
		if( document.getElementById('language_'+option.options[j].value).style.display!='none' ){
			document.getElementById('language_'+option.options[j].value).style.display='none';
		}
	}

	document.getElementById('language_'+jQuery('#language').val()).style.display='block';
}

function choose_filter(field)
{
	window.open('/admin?option=choose_filter&section='+field,'Insert','width=700,height=450,screenX=100,screenY=100,left=100,top=100,status,dependent,alwaysRaised,resizable,scrollbars')
}

window.onload=init;
</script>

<script>
$('#privileges_none').click(function(){
	$('select.privileges').val('0');
	return false;
});

$('#privileges_read').click(function(){
	$('select.privileges').val('1');
	return false;
});

$('#privileges_write').click(function(){
	$('select.privileges').val('2');
	return false;
});

// hash tabs
$(function(){
  var hash = window.location.hash;
  hash && $('ul.nav a[href="' + hash + '"]').tab('show');

  $('.nav-tabs a').click(function (e) {
    $(this).tab('show');
    var scrollmem = $('body').scrollTop() || $('html').scrollTop();
    window.location.hash = this.hash;
    $('html,body').scrollTop(scrollmem);
  });
});

// resize datatables
$('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
    $($.fn.dataTable.tables(true)).DataTable()
       .columns.adjust()
       .responsive.recalc();
});

</script>

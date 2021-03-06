<?php
/*
File:		default.php
Author:		Adam Jimenez
*/

require(dirname(__FILE__) . '/base.php');

function get_tpl_catcher($request)
{
    global $tpl_config, $root_folder;

    // deprecated
    if (is_array($tpl_config['catchers'])) {
        foreach ($tpl_config['catchers'] as $catcher) {
            if (substr($request, 0, strlen($catcher) + 1) == $catcher . '/') {
                return $catcher;
            }
        }
    }
    
    // find closest catcher
    $dir = $request;
    while ($dir = dirname($dir)) {
        if (file_exists($root_folder . '/_tpl/' . $dir . '.catcher.php')) {
            return $dir . '.catcher';
        } elseif ($dir == dirname($dir)) {
            return false;
        }
    }    

    return false;
}

function trigger_404()
{
    throw new Exception(404);
}

function parse_request(): string
{
    $request = rawurldecode($_SERVER['REQUEST_URI']);
    
    // strip query string
    $pos = strpos($request, '?');
    if ($pos) {
        $request = substr($request, 0, $pos);
    }

    // append index if directory
    if (!$request || ends_with($request, '/')) {
        $request .= 'index';
    }

    // strip prepending slash
    return ltrim($request, '/');
}

function get_include($request)
{
    global $tpl_config, $root_folder, $vars, $cms, $content;
    
    // enforce ssl
    if (!$_SERVER['HTTPS'] && $tpl_config['ssl']) {
        redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    // strip file extension from url
    } elseif (strstr($request, '.php')) {
        redirect('http'.($_SERVER['HTTPS'] ? 's' : '').'://' . $_SERVER['HTTP_HOST'] . str_replace('.php', '',$_SERVER['REQUEST_URI']));
    // redirect if a folder and missing trailing /
    } elseif (is_dir($root_folder . '/_tpl/' . $request) || in_array($request, $tpl_config['catchers']) || file_exists($root_folder . '/_tpl/' . $request . '.catcher.php')) {
        redirect('/' . $request . '/', 301);
    // check if template exists
    } elseif (file_exists($root_folder . '/_tpl/' . $request . '.php')) {
        return $root_folder . '/_tpl/' . $request . '.php';
    // check domain redirects
    } elseif ($tpl_config['redirects']['http://' . $_SERVER['HTTP_HOST'] . '/']) {
        $redirect = $tpl_config['redirects']['http://' . $_SERVER['HTTP_HOST'] . '/'];
        redirect($redirect, 301);
    // check redirects list
    } elseif ($tpl_config['redirects'][$request]) {
        $redirect = $tpl_config['redirects'][$request];
        redirect($redirect, 301);
    // check catchers
    } elseif ($catcher = get_tpl_catcher($request)) {
        return $root_folder . '/_tpl/' . $catcher . '.php';
    } else {
        // check pages section
        if (in_array('pages', $vars['sections'])) {
            $content = $cms->get('pages', $request);

            if ($content && file_exists('_tpl/pages.php')) {
                return $root_folder . '/_tpl/pages.php';
            }
        }

        // deprecated: check if catch all file exists
        if (file_exists($root_folder . '/_inc/catch_all.php')) {
            return $root_folder . '/_inc/catch_all.php';
        }
    }
}

$request = parse_request();

//check for predefined pages
switch ($request) {
    case 'admin':
        if (!$cms) {
            die('Error: db is not configured');
        }

        $cms->admin();
        exit;
    break;
    case 'sitemap.xml':
        if (!file_exists($root_folder . '/_tpl/sitemap.xml.php')) {
            require(dirname(__FILE__) . '/core/sitemap.xml.php');
            exit;
        }
    break;
    case 'logout':
        $auth->logout();
        redirect('/');
    break;
}

// current tab
$sections = explode('/', $request);

// templates
$include_file = get_include($request);

// get include content
$time_start = microtime(true);
if (!$include_file || 'template' == end($sections)) {
    $trigger_404 = true;
} elseif ($include_file) {
    ob_start();
    try {
        require($include_file);
    } catch (Exception $e) {
        switch ($e->getMessage()) {
            case 404:
                $trigger_404 = true;
            break;
            default:
                $msg = nl2br($e->getMessage() . "\n" . $e->getTraceAsString());
                error_handler(E_USER_ERROR, $msg, $e->getFile(), $e->getLine());
            break;
        }
    }
    $include_content = ob_get_contents();
    ob_end_clean();
}

// handle 404
if ($trigger_404) {
    header('HTTP/1.0 404 Not Found');

    if (file_exists($root_folder . '/_tpl/404.php')) {
        ob_start();
        require($root_folder . '/_tpl/404.php');
        $include_content = ob_get_contents();
        ob_end_clean();
    } else {
        $include_content = '<h1>404 - Page can not be found</h1>';
    }
}

// get page title from h1 tag if it doesn't exist
if (!$title && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $include_content, $matches)) {
    $title = trim(preg_replace("/\r|\n/", '', strip_tags($matches[1])));
}

// find closest template.php
$dir = $request;
while ($dir = dirname($dir)) {
    if (include($root_folder . '/_tpl/' . $dir . '/template.php')) {
        break;
    } else if ($dir == dirname($dir)) {
        die('template not found');
    }
}

// debug page speed
if ($auth->user['admin'] && $_GET['debug']) {
    $time_end = microtime(true);
    echo '<span style="color:yellow; background: red; position:absolute; top:0; left:0; z-index: 100;">Loaded in ' . number_format($time_end - $time_start, 3) . 's</span>';
}

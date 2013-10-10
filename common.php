<?php

define('DS', '/');
define('ROOT', dirname(__FILE__) . DS);
define('CACHE', ROOT . 'tmp' . DS);

require './lib_proxy.php';
$tag = '44haitaoad1-20';

/**
 * 缓存静态资源(css/js/img)
 * @param type $url
 * @return type
 */
function getCacheStatic($url) {

	global $proxy;
	$md5 = md5($url);
	$path = "static" . DS . "{$md5[0]}" . DS . $md5;
	$static_cache = cache($path, null, 86400);
	if (!$static_cache) {
		$static_cache = $proxy->request($url);
		//去除UTF BOM头
		if (substr($static_cache, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
			$static_cache = substr($static_cache, 3);
		}
		$is_static = false;
		if (preg_match('/(.jpg|.gif|.bmp|.png|.js|.css|.ico)/i', $url)) {
			$is_static = true;
		}

		if ($static_cache && $is_static)
			cache($path, $proxy->response_headers . "{||}" . $static_cache);
	}else {

		//恢复缓存的头部
		list($proxy->response_headers, $static_cache) = explode("{||}", $static_cache);
		$proxy->return_response(true);
	}
	return $static_cache;
}

/**
 * Reads/writes temporary data to cache files or session.
 *
 * @param  string $path	File path within /tmp to save the file.
 * @param  mixed  $data	The data to save to the temporary file.
 * @param  mixed  $expires second.
 * @param  string $target  The target of the cached data; either 'cache' or 'public'.
 * @return mixed  The contents of the temporary file.
 */
function cache($path, $data = null, $expires = 3600, $target = 'cache') {

    switch (strtolower($target)) {
        case 'cache':
            $filename = CACHE . $path;
            break;
        case 'public':
            $filename = WWW_ROOT . $path;
            break;
        default:
            $filename = $path;
            break;
    }

    $filename = str_replace('//', '/', $filename);
    $filename = str_replace('?', '/', $filename);
    $filename = str_replace('=', '/', $filename);
    $now = time();
    $timediff = $expires;
    $filetime = @filemtime($filename);

    if ($data == null) {
        // Read data from file
        if (file_exists($filename) && $filetime !== false) {
            if ($filetime + $timediff < $now) {
                // File has expired
                @unlink($filename);
            } else {
                $data = file_get_contents($filename);
                $data = unserialize($data);
            }
        }
    } else {
        //writeFile($filename, $data);
        mkdirs($filename);
        file_put_contents($filename, serialize($data));
    }
    return $data;
}

/**
 * An easier way to do mkdirs : recursion.
 *
 * @access public
 * @param string $strPath
 * @param integer $mode
 * @return bool
 */
function mkdirs($strPath = '', $mode = 777) {

    $strPath = dirname($strPath);
    if (is_dir($strPath))
        return true;

    if (!mkdirs($strPath, $mode))
        return false;

    return mkdir($strPath);
}

function amazon_r($hit){

    global $tag;
	$jump_url = preg_replace('/(tag=[a-z0-9_\-]+)/', 'tag='.$tag, $hit[1]);
	return 'href="'.$jump_url.'"';
}
?>
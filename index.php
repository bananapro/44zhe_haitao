<?php

require './common.php';

$target = 'www.123haitao.com';
$proxy = new Proxy($target);

$path = @$_GET['path'];
if (!$path) {
	$path = '/';
}
else {
	unset($_GET['path']);
}

if ($_GET) {
	$param = '?' . http_build_query($_GET);
}
else {
	$param = '';
}

$uri = $target . $path . $param;
$uri = trim($uri, '?');

if($_GET['l'] == 'go'){

	setcookie("sbear", $_GET['c'], time() + 180 * 24 * 3600, '/'); //植入流量cookie
	header('Location: http://haitao.44zhe.com/redirect/'.$_GET['r']);
	die();
}

if (stripos($path, '/redirect/') !== false) {
	$proxy->enable_follow = false;

	if ($_COOKIE['sbear'] || stripos($_SERVER["HTTP_USER_AGENT"], 'ProxySG')!==false) {//插件来的流量
		//插入landing js
		$j = array_pop(explode('/redirect/', $path));
		$jump_url = base64_decode(urldecode($j));
	}
	else if (stripos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
		//本站点击流量修改amazon tag标签
		getCacheStatic($uri);
		$jump_url = preg_replace('/(\tag=[a-z0-9_\-]+)/', 'tag='.$tag, $proxy->redirect_url);
		header('location:' . $jump_url);
		die();
	}
	else {
		//amazon调查流量，无需landing直接打回
		$j = array_pop(explode('/redirect/', $path));
		$jump_url = base64_decode(urldecode($j));
		header('location:' . $jump_url);
		die();
	}

	$landing_page = <<<EOT
<html><head><title>Waiting</title><meta content="text/html;charset=utf-8" http-equiv="Content-Type" /></head><body><img src="http://ir-na.amazon-adsystem.com/e/ir?t=44haitaoad1-20&l=ur2&o=1" width="1" height="1"/></body></html>
<script>
var referLink = document.createElement('a');
referLink.href = '{$jump_url}';
referLink.target = '_self';
document.body.appendChild(referLink);
referLink.click();
</script>
EOT;

	echo $landing_page;
	die();
}
else {
	$page = getCacheStatic($uri);
	if (!preg_match('/(.jpg|.gif|.bmp|.png|.js|.css|.ico)/i', $uri)) {

		$page = str_replace("\n", '', $page);
		$page = str_replace('<script type="text/javascript" id="bdshare_js" data="type=tools" ></script>', '', $page);
		$page = preg_replace('/<div[^<]+?id="__crond">(.+?)div>/', '', $page);
		$page = preg_replace('/<div[^<]+?col-xs-9 col-sm-9(.+?)div>/', '<div class="col-xs-9 col-sm-9">© copyright 2010-2012 海淘宝贝. All Rights Reserved. 粤ICP备12068830号-1</div>', $page);
		$page = preg_replace_callback('/href="([^"]+?tag[^"]+?)"/', 'amazon_r', $page);
		$page = preg_replace('/<script[^<]+?hm.baidu.com(.+?)script>/', '', $page);
		$page = preg_replace('/<script[^<]+?google-analytics.com(.+?)script>/', '', $page);
		$page = preg_replace('/<script[^<]+?pagead2.googlesyndication.com(.+?)script>/', '', $page);
		$page .= '<style>.ht-news-rolling,.ht-side-bar-sns,.ht-side-bar-code,.ht-buy-list{display:none}</style>';
		$page .= '<script>$(".ht-suggest-follow").html("<a href=\"http://www.amazon.com/?_encoding=UTF8&camp=1789&creative=9325&linkCode=ur2&tag='.$tag.'\" target=\"_blank\"><img src=/amazon.jpg /></a>")</script>';
	}

	if ($path == '/'){
		$page .= <<<EOT

<div id="stat" style="display:none">
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-44932079-1', '44zhe.com');
ga('send', 'pageview');

</script>

<script type="text/javascript">
var _bdhmProtocol = (("https:" == document.location.protocol) ? " https://" : " http://");
document.write(unescape("%3Cscript src='" + _bdhmProtocol + "hm.baidu.com/h.js%3Fb5021771b4c24818e7503528b034d0a8' type='text/javascript'%3E%3C/script%3E"));
</script>
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-44932079-1', '44zhe.com');
ga('send', 'pageview');

</script>
</div>
EOT;

		header('Content-Length: ' . strlen($page)); //修正页面大小
	}
}

echo $page;
?>
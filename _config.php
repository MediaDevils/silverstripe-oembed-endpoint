<?php
if(isset($_GET['url']) && strpos($_SERVER['QUERY_STRING'], '&url=') !== false) {
	$parts = explode('&', $_SERVER['QUERY_STRING']);
	$url = array_shift($parts);
	parse_str($url, $_GET);
	$oembedurl = array_pop($parts);
	$oembedurl = explode("=", $oembedurl);
	$_GET['oembedurl'] = $oembedurl[1];
}

Director::addRules(10, array(
	'oembed' => 'oEmbedEndpoint_Controller'
));

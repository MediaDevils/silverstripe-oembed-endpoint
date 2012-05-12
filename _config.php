<?php
if(isset($_GET['url']) && strpos($_SERVER['QUERY_STRING'], '&url=') !== false) {
	$parts = explode('&', $_SERVER['QUERY_STRING']);
	$url = array_shift($parts);
	parse_str($url, $_GET);
	array_shift($parts);
	
	foreach($parts as $part) {
		$part = explode("=", $part);
		if($part[0] == "url") $_GET['oembedurl'] = $part[1];
		else $_GET[$part[0]] = $part[1];
	}
}

Director::addRules(10, array(
	'oembed' => 'oEmbedEndpoint_Controller'
));

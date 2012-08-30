<?php
class oEmbedEndpoint_Controller extends Controller {
	public function index($request) {
		$url = strtolower(urldecode($request->getVar('oembedurl')));
		$maxwidth = $request->getVar('maxwidth');
		$maxheight = $request->getVar('maxheight');
		$format = $request->getVar('format');
		
		if(!$provider = $this->getEndpointForURL($url)) {
			return $this->httpError(oEmbedEndpoint_Response::RESPONSE_NOT_IMPLEMENTED, "Not Implemented");
		}
		

		$response = $provider::get_response($url, $request->getVars());
		$http = $this->getResponse();
		
		if(is_int($response)) {
			switch($response) {
				case oEmbedEndpoint_Response::RESPONSE_NOT_FOUND:
					return $this->httpError(oEmbedEndpoint_Response::RESPONSE_NOT_FOUND, "Not Found");
				case oEmbedEndpoint_Response::RESPONSE_NOT_IMPLEMENTED:
					return $this->httpError(oEmbedEndpoint_Response::RESPONSE_NOT_IMPLEMENTED, "Not Implemented");
				case oEmbedEndpoint_Response::RESPONSE_UNAUTHORIZED:
					return $this->httpError(oEmbedEndpoint_Response::RESPONSE_UNAUTHORIZED, "Unauthorized");
			}
			return Array();
		} elseif(!is_a($response, 'oEmbedEndpoint_Response')) {
			return $this->httpError(oEmbedEndpoint_Response::RESPONSE_NOT_FOUND, "Not Found");
		}
		
		switch(strtolower($format)) {
			default:
			case 'json':
				$http->addHeader("Content-Type", "application/json");
				return $response->toJSON();
			case 'xml':
				$http->addHeader("Content-Type", "text/xml");
				return $response->toXML();
		}
	}
	
	public function getEndpointForURL($url) {
		$providers = ClassInfo::subclassesFor('oEmbedEndpoint');
		
		foreach($providers as $provider) {
			$providerReflection = new ReflectionClass($provider);
			if($providerReflection->isAbstract()) continue;
			if(!is_string($provider::$scheme) || strlen($provider::$scheme) <= 0) continue;
			if(!$provider::match_scheme($url)) continue;
			
			return $provider;
		}
		return false;
	}
	
	public static function EndpointForURL($url) {
		return singleton(__CLASS__)->getEndpointForURL($url);
	}
	
	public static function addDiscovery($url, $title = null, $type = 'json') {
		$url = urlencode($url);
		$endpointURL = Director::absoluteBaseURL()."oembed?url={$url}&format={$type}";
		if(is_string($title)) $title = "title=\"".htmlentities($title)."\"";
		
		$html = "<link rel=\"alternate\" type=\"application/{$type}+oembed\" href=\"{$endpointURL}\" {$title} />";
		
		Requirements::insertHeadTags($html, md5($url));
	}
	
	public static function LocalResponse($url) {
		if($endpoint = self::EndpointForURL($url)) {
			$response = $endpoint::get_response($url);
			if($response && is_a($response, 'oEmbed_Result_Type')) {
				$embed = new oEmbed_Result();
				if($embed) return $embed->loadData($response->toJSON(), 'json');
				else return false;
			} else return false;
		} else return false;
	}
}

abstract class oEmbedEndpoint {
	public static $scheme;
	
	public static function match_scheme($url) {
		$urlInfo = parse_url($url);
		$schemeInfo = parse_url(static::$scheme);
		foreach($schemeInfo as $k=>$v) {
			if(!array_key_exists($k, $urlInfo)) {
				return false;
			}
			if(strpos($v, '*') !== false) {
				$v = preg_quote($v, '/');
				$v = str_replace('\*', '.*', $v);
				if($k == 'host') {
					$v = str_replace('*\.', '*', $v);
				}
				if(!preg_match('/' . $v . '/', $urlInfo[$k])) {
					return false;
				}
			} elseif(strcasecmp($urlInfo[$k], $v)) {
				return false;
			}
		}
		return true;
	}
	
	public static function get_response($url, Array $options = array()) {
		
	}
}

class oEmbedEndpoint_Response {
	const RESPONSE_NOT_FOUND = 404;
	const RESPONSE_NOT_IMPLEMENTED = 501;
	const RESPONSE_UNAUTHORIZED = 401;
	
	const OEMBED_VERSION = '1.0';
	
	public $parameters = array();

	public function __construct(
		$type,
		$title = null,
		$author_name = null,
		$author_url = null,
		$provider_name = null,
		$provider_url = null,
		$cache_age = null,
		$thumbnail_url = null,
		$thumbnail_width = null,
		$thumbnail_height = null
	) {
		$this->parameters = array(
			"type" => $type,
			"version" => self::OEMBED_VERSION,
			"title" => $title,
			"author_name" => $author_name,
			"author_url" => $author_url,
			"cache_age" => $cache_age,
			"thumbnail_url" => $thumbnail_url,
			"thumbnail_width" => $thumbnail_width,
			"thumbnail_height" => $thumbnail_height
		);
	}
	
	public function addCustomParameter($key, $value) {
		$this->parameters[$key] = $value;
	}
	
	public function toJSON() {
		$parameters = $this->parameters;
		foreach($parameters as $key => $parameter) if(is_null($parameter)) unset($parameters[$key]);
		return json_encode($parameters);
	}
	
	public function toXML() {
		$document = new DOMDocument("1.0", "utf-8");
		$document->appendChild($oembed = $document->createElement("oembed"));
		
		foreach($this->parameters as $name => $value) {
			if($name == "html") $value = htmlentities($value);
			$oembed->appendChild($document->createElement($name, $value));
		}
		
		return $document->saveXML();
	}
}

class oEmbedEndpoint_Photo extends oEmbedEndpoint_Response {
	public function __construct(
		$url,
		$width,
		$height,
		$title = null,
		$author_name = null,
		$author_url = null,
		$provider_name = null,
		$provider_url = null,
		$cache_age = null,
		$thumbnail_url = null,
		$thumbnail_width = null,
		$thumbnail_height = null
	) {
		parent::__construct(
			'photo',
			$title,
			$author_name,
			$author_url,
			$provider_name,
			$provider_url,
			$cache_age,
			$thumbnail_url,
			$thumbnail_width,
			$thumbnail_height
		);
		
		$this->parameters = array_merge(
			$this->parameters,
			array(
				"url" => $url,
				"width" => $width,
				"height" => $height
			)
		);
	}
}

class oEmbedEndpoint_Video extends oEmbedEndpoint_Response {
	public function __construct(
		$html,
		$width,
		$height,
		$title = null,
		$author_name = null,
		$author_url = null,
		$provider_name = null,
		$provider_url = null,
		$cache_age = null,
		$thumbnail_url = null,
		$thumbnail_width = null,
		$thumbnail_height = null
	) {
		parent::__construct(
			'video',
			$title,
			$author_name,
			$author_url,
			$provider_name,
			$provider_url,
			$cache_age,
			$thumbnail_url,
			$thumbnail_width,
			$thumbnail_height
		);
		
		$this->parameters = array_merge(
			$this->parameters,
			array(
				"html" => $html,
				"width" => $width,
				"height" => $height
			)
		);
	}
}

class oEmbedEndpoint_Rich extends oEmbedEndpoint_Response {
	public function __construct(
		$html,
		$width,
		$height,
		$title = null,
		$author_name = null,
		$author_url = null,
		$provider_name = null,
		$provider_url = null,
		$cache_age = null,
		$thumbnail_url = null,
		$thumbnail_width = null,
		$thumbnail_height = null
	) {
		parent::__construct(
			'rich',
			$title,
			$author_name,
			$author_url,
			$provider_name,
			$provider_url,
			$cache_age,
			$thumbnail_url,
			$thumbnail_width,
			$thumbnail_height
		);
		
		$this->parameters = array_merge(
			$this->parameters,
			array(
				"html" => $html,
				"width" => $width,
				"height" => $height
			)
		);
	}
}

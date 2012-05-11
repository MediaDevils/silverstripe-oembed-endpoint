<?php
class oEmbedEndpoint_Controller extends Controller {
	public function index() {
		$providers = ClassInfo::subclassesFor('oEmbedEndpoint');
	
		$request = $this->getRequest();
		
		$url = url_decode($request->getVar('url'));
		$maxwidth = $request->getVar('maxwidth');
		$maxheight = $request->getVar('maxheight');
		$format = $request->getVar('format');
		
		foreach($providers as $provider) {
			$providerReflection = new ReflectionClass($provider);
			if($providerReflection->isAbstract()) continue;
			if(!is_string($provider::$scheme) || strlen($provider::$scheme) <= 0) continue;
			if(!$provider::match_scheme($url)) continue;
			
			$response = $provider::get_response($url, $maxwidth, $maxheight, $format);
			$http = $this->getResponse();
			
			if(is_int($response)) {
				switch($response) {
					case oEmbedEndpoint_Response::RESPONSE_NOT_FOUND:
						$http->setStatusCode(
							oEmbedEndpoint_Response::RESPONSE_NOT_FOUND,
							"Not Found"
						);
						break;
					case oEmbedEndpoint_Response::RESPONSE_NOT_IMPLEMENTED:
						$http->setStatusCode(
							oEmbedEndpoint_Response::RESPONSE_NOT_IMPLEMENTED,
							"Not Implemented"
						);
						break;
					case oEmbedEndpoint_Response::RESPONSE_UNAUTHORIZED:
						$http->setStatusCode(
							oEmbedEndpoint_Response::RESPONSE_UNAUTHORIZED,
							"Unauthorized"
						);
						break;
				}
				continue; // Something went wrong, move on to the next match
			}
			
			switch(strtolower($format)) {
				default:
				case 'json':
					$http->addHeader("Content-Type", "application/json");
					$http->setBody($response->getJSON());
					break;
				case 'xml':
					$http->addHeader("Content-Type", "text/xml");
					$http->setBody($response->getXML());
					break;
			}
			
			break; // Only match once
		}
	}
}

abstract class oEmbedEndpoint {
	public static $scheme;
	
	public static function match_scheme($url) {
		$urlInfo = parse_url($url);
		$schemeInfo = parse_url(self::$scheme);
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
	
	abstract public static function get_response($url, $maxwidth = null, $maxheight = null, $format = null);
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
		$parameters = array(
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
	
	public function toJSON() {
		return json_encode($this->parameters);
	}
	
	public function toXML() {
		$document = new DOMDocument("1.0", "utf-8");
		$document->appendChild($oembed = $document->createElement("oembed"));
		
		foreach($this->parameters as $name => $value) $document->appendChild($document->createElement($name, $value));
		
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

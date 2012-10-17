<?

class UnleashedAPI extends Object {
	
	private static $id;
	private static $key;

	static function set_settings($id, $key) {
		self::$id = $id;
		self::$key = $key;
	}

	protected static $format = 'json';
	protected static $allowed_formats = array('json', 'xml');

	static function set_format($format) {
		if(in_array($format, self::$allowed_formats)) {
			self::$format = $format;
		}
	}

	static function get_format() {return self::$format;}

	/**
	 * IMPORTANT : Do not use the return values (Test post already created Product and it returns ProductCode = NULL), (Test post new order and it returns GUID = null for the Customer)
	 * Add/Update Product XML : InternalServerError
	 */
	static function post($class, $uID, $values, $format = null) {
		$signature = base64_encode(hash_hmac('sha256', '', self::$key, true));

		if(! $format) {
			$format = self::$format;
		}

		$headerFormat = "application/$format";

		$headers = array(
			"Content-Type: $headerFormat",
			"Accept: $headerFormat",
			"api-auth-id: " . self::$id,
			"api-auth-signature: $signature"
		);
		
		$function = "array2$format";
		$values = $format == 'xml' ? self::$function($values, substr($class, 0, -1)) : Convert::$function($values);

		try { 
			$curl = curl_init("https://api.unleashedsoftware.com/$class/$uID");
			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_TIMEOUT, 20);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $values);
			$result = curl_exec($curl);
			error_log($result); 
			curl_close($curl);
			$function = "{$format}2array";
			$result = Convert::$function($result);
			return is_string($result) || ($format == 'xml' && isset($result['ValidationError'])) ? false : $result;
		}
		catch(Exception $e) { 
			error_log("Unleashed Error: $e"); 
		}
	}

	/**
	 * Returns an array or an array of arrays or false 
	 */
	static function get($class, $filters = null) {
		$params = '';
		if(is_array($filters)) {
			$params = http_build_query($filters);
		}

		$signature = base64_encode(hash_hmac('sha256', $params, self::$key, true));

		if($params) {
			$params = "?$params";
		}

		$format = 'application/' . self::$format;

		$headers = array(
			"Content-Type: $format",
			"Accept: $format",
			"api-auth-id: " . self::$id,
			"api-auth-signature: $signature"
		);

		try {
			$curl = curl_init("https://api.unleashedsoftware.com/$class$params");
			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_TIMEOUT, 20); 
			$result = curl_exec($curl); 
			error_log($result); 
			curl_close($curl);
			$function = self::$format . '2array';
			$result = Convert::$function($result);
			if(self::$format == 'json') {
				if(is_array($result) && isset($result['Items'])) {
					$result = $result['Items'];
					foreach($result as $index => $object) {
						$result[$index] = get_object_vars($object);
					}
				}
			}
			else if(self::$format == 'xml') { // $result is an array or empty string
				if($result == '') {
					$result = false;
				}
				else if(count($result) == 1) { // List of objects or no result
					$result = array_pop($result);
					if(is_array($result)) { // There are results
						if(! isset($result[0])) {
							$result = array($result);
						}
					}
					else { // No result
						$result = false;
					}
				}
			}
			return $result;
		}
		catch(Exception $e) { 
			error_log("Unleashed Error: $e"); 
		}
	}

	static function get_by_guid($class, $guid) {
		return self::get("$class/$guid");
	}

	static function array2xml($array, $name) {
		$xml = new SimpleXMLElement("<$name/>");
		self::array2xmlRecursive($xml, $array);
		
		$xml = $xml->asXML();

		// must remove the <xml version="1.0"> node if present, the API does not want it
		$pos = strpos($xml, '<?xml version="1.0"?>');
		if($pos !== false) {
			$xml = str_replace('<?xml version="1.0"?>', '', $xml);
		}
		
		// if the data does not have the correct xml namespace (xmlns) then add it
		$pos = strpos($xml, 'xmlns="http://api.unleashedsoftware.com/version/1"');
		if($pos === false) {
			// there should be a better way than this
			// using preg_replace with count = 1 will only replace the first occurance
			$xml = preg_replace('/>/i', ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://api.unleashedsoftware.com/version/1">', $xml, 1);
		}

		return $xml;
	}

	static function array2xmlRecursive(&$pointer, $array) {
		foreach($array as $index => $value) {
			if(is_array($value)) {
				if(array_values($value) === $value) { // Associative Array
					foreach($value as $subValue) {
						$child = $pointer->addChild($index);
						self::array2xmlRecursive($child, $subValue);
					}
				}
				else {
					$child = $pointer->addChild($index);
					self::array2xmlRecursive($child, $value);
				}
			}
			else {
				$pointer->$index = $value;
			}
		}
	}
}
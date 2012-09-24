<?

class UnleashedObject extends Object {
	
	private static $api_id;
	private static $api_key;

	static function set_api_settings($id, $key) {
		self::$api_id = $id;
		self::$api_key = $key;
	}

	static $format = 'json';
	static $allowed_formats = array('json', 'xml');

	static function set_format($format) {
		if(in_array($format, self::$allowed_formats) && method_exists('Convert', "{$format}2array")) {
			self::$format = $format;
		}
	}

	static function post($class) {

	}

	static function get($class, $filter = '') {
		$params = '';
		if($filter && is_array($filter)) {
			$params = http_build_query($filter);
		}

		$signature = base64_encode(hash_hmac('sha256', $params, self::$api_key, true));

		if($params) {
			$params = "?$params";
		}

		$format = 'application/' . self::$format;

		$headers = array(
			"Content-Type: $format",
			"Accept: $format",
			"api-auth-id: " . self::$api_id,
			"api-auth-signature: $signature"
		);

		try { 
			$curl = curl_init("https://api.unleashedsoftware.com/$class$params"); 
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
			return $result['Items'];
		}
		catch(Exception $e) { 
			error_log("Unleashed Error: $e"); 
		}
	}
}
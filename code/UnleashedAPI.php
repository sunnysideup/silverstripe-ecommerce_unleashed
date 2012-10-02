<?

class UnleashedAPI extends Object {
	
	private static $id;
	private static $key;

	static function set_settings($id, $key) {
		self::$id = $id;
		self::$key = $key;
	}

	static $format = 'json';
	static $allowed_formats = array('json', 'xml');

	static function set_format($format) {
		if(in_array($format, self::$allowed_formats) && method_exists('Convert', "{$format}2array")) {
			self::$format = $format;
		}
	}

	static function post($class, $uID, $values) {
		$class .= "/$uID";

		$signature = base64_encode(hash_hmac('sha256', '', self::$key, true));

		$format = 'application/' . self::$format;

		$headers = array(
			"Content-Type: $format",
			"Accept: $format",
			"api-auth-id: " . self::$id,
			"api-auth-signature: $signature"
		);

		$function = 'array2' . self::$format;
		$values = Convert::$function($values);
		
		try { 
			$curl = curl_init("https://api.unleashedsoftware.com/$class");
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
			$function = self::$format . '2array';
			return Convert::$function($result);
		}
		catch(Exception $e) { 
			error_log("Unleashed Error: $e"); 
		}
	}

	static function post_test() {
		$class = 'Customers';
		$values = array('CustomerCode' => '1', 'CustomerName' => 'Test Name');
		$uID = 'eaotd0zs-nmoj-z86d-76yv-ue0o8793ajci';

		$class .= "/$uID";

		$signature = base64_encode(hash_hmac('sha256', '', self::$key, true));

		$format = 'application/' . self::$format;

        $headers = array(
                "Content-Type: $format",
                "Accept: $format",
                "api-auth-id: " . self::$id,
                "api-auth-signature: $signature"
        );

		$function = 'array2' . self::$format;
        $values = Convert::$function($values);

        try { 
            $curl = curl_init("https://api.unleashedsoftware.com/$class");
            curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($curl, CURLINFO_HEADER_OUT, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $values);
            $result = curl_exec($curl);var_dump($result);
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

    static function post_update_test($notes) {
    	$customers = self::get('Customers');
    	$customer = $customers[0];
    	$guid = $customer->Guid;
    	self::post('Customers', $guid, array('Notes' => $notes));
    }

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

	static function get_by_guid($class, $guid) {
		$uObject = self::get("$class/$guid");
		if(is_array($uObject) && count($uObject) == 1) {
			return $uObject[0];
		}
	}

	/*protected static function query($type, $class, $uID = null, $values = null) {
		if($type != 'GET' && $type != 'POST') return;

		$segment = $class;
		if($uID) {
			$segment .= "/$uID";
		}

		$params = '';
		if($type == 'GET' && is_array($values)) {
			$params = http_build_query($values);
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
			$curl = curl_init("https://api.unleashedsoftware.com/$segment$params"); 
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_TIMEOUT, 20);
			if($type == 'POST') {
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $values);
			}
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
	}*/
}
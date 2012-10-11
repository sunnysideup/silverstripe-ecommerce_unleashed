<?php

	// configuration data
	// must use your own id and key with no extra whitespace
	$api = 'https://api.unleashedsoftware.com/';
	$apiId = 'your id here';
	$apiKey = 'your key here';
	
    	
	// Get the request signature:
	// Based on your API id and the request portion of the url 
	// - $request is only any part of the url after the "?"
	// - use $request = "" if there is no request portion 
	// - for GET $request will only be the filters eg ?customerName=Bob
	// - for POST $request will usually be an empty string
	// - $request never includes the "?"
	// Using the wrong value for $request will result in an 403 forbidden response from the API
	function getSignature($request, $key) {
		return base64_encode(hash_hmac('sha256', $request, $key, true)); 
	}
	
	// Create the curl object and set the required options
	// - $api will always be https://api.unleashedsoftware.com/
	// - $endpoint must be correctly specified
	// - $requestUrl does include the "?" if any
	// Using the wrong values for $endpoint or $requestUrl will result in a failed API call
	function getCurl($id, $key, $signature, $endpoint, $requestUrl, $format) {
		global $api;
		
		$curl = curl_init($api . $endpoint . $requestUrl); 
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true); 
        curl_setopt($curl, CURLINFO_HEADER_OUT, true); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/$format", 
                 "Accept: application/$format", "api-auth-id: $id", "api-auth-signature: $signature")); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		// these options allow us to read the error message sent by the API
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_HTTP200ALIASES, range(400, 599));
		
		return $curl;
	}
	
	// GET something from the API
	// - $request is only any part of the url after the "?"
	// - use $request = "" if there is no request portion 
	// - for GET $request will only be the filters eg ?customerName=Bob
	// - $request never includes the "?"
	// Format agnostic method.  Pass in the required $format of "json" or "xml"
	function getUnleashed($id, $key, $endpoint, $request, $format) {
		$requestUrl = ""; 
		if (!empty($request)) $requestUrl = "?$request"; 
					
		try {
			// calculate API signature
			$signature = getSignature($request, $key);		
			// create the curl object
			$curl = getCurl($id, $key, $signature, $endpoint, $requestUrl, $format);	
			// GET something
			$curl_result = curl_exec($curl); 
			error_log($curl_result); 
			curl_close($curl); 
			return $curl_result;	
		} 
		catch (Exception $e) { 
			error_log('Error: ' + $e); 			
		}
	}
	
	// POST something to the API
	// - $request is only any part of the url after the "?"
	// - use $request = "" if there is no request portion 
	// - for POST $request will usually be an empty string
	// - $request never includes the "?"
	// Format agnostic method.  Pass in the required $format of "json" or "xml"
	function post($id, $key, $endpoint, $format, $dataId, $data) {
		if (!isset($dataId, $data)) { return null; }
		
		try {
			// calculate API signature
			$signature = getSignature("", $key);
			// create the curl object. 
			// - POST always requires the object's id
			$curl = getCurl($id, $key, $signature, "$endpoint/$dataId", "", $format);
			// set extra curl options required by POST
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			
			// POST something			
			$curl_result = curl_exec($curl); 		
			error_log($curl_result); 
			curl_close($curl); 
			return $curl_result;	
		} 
		catch (Exception $e) { 
			error_log('Error: ' + $e); 			
		}
	}
	
	// GET in XML format
	// - gets the data from the API and converts it to an XML object
	function getXml($id, $key, $endpoint, $request) {
		// GET it
		$xml = getUnleashed($id, $key, $endpoint, $request, "xml");
		// Convert to XML object and return
		return new SimpleXMLElement($xml);
	}
	
	// POST in XML format
	// - the object to POST must be a valid XML object. Not stdClass, not array, not associative.
	// - converts the object to string and POSTs it to the API
	function postXml($id, $key, $endpoint, $dataId, $data) {
	
		$xml = $data->asXML();

		// must remove the <xml version="1.0"> node if present, the API does not want it
		$pos = strpos($xml, '<?xml version="1.0"?>');
		if ($pos !== false) {
			$xml = str_replace('<?xml version="1.0"?>', '', $xml);
		}
		
		// if the data does not have the correct xml namespace (xmlns) then add it
		$pos1 = strpos($xml, 'xmlns="http://api.unleashedsoftware.com/version/1"');
		if ($pos1 === false) {
			// there should be a better way than this
			// using preg_replace with count = 1 will only replace the first occurance
			$xml = preg_replace('/>/i',' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://api.unleashedsoftware.com/version/1">',$xml,1);
		}
				
		// POST it
		$posted = post($id, $key, $endpoint, "xml", $dataId, $xml );
		// Convert to XML object and return
		// - the API always returns the POSTed object back as confirmation
		return new SimpleXMLElement($posted);
	}
	
	// GET in JSON format
	// - gets the data from the API and converts it to an stdClass object	
	function getJson($id, $key, $endpoint, $request) {
		// GET it, decode it, return it
		return json_decode(getUnleashed($id, $key, $endpoint, $request, "json"));
	}
	
	// POST in JSON format
	// - the object to POST must be a valid stdClass object. Not array, not associative.
	// - converts the object to string and POSTs it to the API
	function postJson($id, $key, $endpoint, $dataId, $data) {
		// POST it, return the API's response		
		return post($id, $key, $endpoint, "json", $dataId, json_encode($data));
	}
	
	// Example method: GET customer list in xml or json
	function getCustomers($format) {
		global $apiId, $apiKey;
		
		if ($format == "xml") 
			return getXml($apiId, $apiKey, "Customers", "");
		else
			return getJson($apiId, $apiKey, "Customers", "");
	}
	
	// Example method: GET customer list, filtered by name, in xml or json	
	function getCustomersByName($customerName,$format) {
		global $apiId, $apiKey;
		if ($format == "xml") 
			return getXml($apiId, $apiKey, "Customers", "customerName=$customerName");
		else
			return getJson($apiId, $apiKey, "Customers", "customerName=$customerName");		
	}
	
	// Example method: POST a customer in xml or json	
	function postCustomer($customer,$format) {
		global $apiId, $apiKey;
			
		if ($format == "xml") 
			return postXml($apiId, $apiKey, "Customers", $customer->Guid, $customer);	
		else
			return postJson($apiId, $apiKey, "Customers", $customer->Guid, $customer);	
	}
	
	// Example method: POST a purchase order in xml or json	
	function postPurchaseOrder($purchase,$format) {
		global $apiId, $apiKey;
						
		if ($format == "xml") 
			return postXml($apiId, $apiKey, "PurchaseOrders", $purchase->Guid, $purchase);	
		else
			return postJson($apiId, $apiKey, "PurchaseOrders", $purchase->Guid, $purchase);	
	}

	// Example method: POST a sale invoice in xml or json	
	function postSaleInvoice($sale,$format) {
		global $apiId, $apiKey;
						
		if ($format == "xml") 
			return postXml($apiId, $apiKey, "SalesInvoices", $sale->Guid, $sale);	
		else
			return postJson($apiId, $apiKey, "SalesInvoices", $sale->Guid, $sale);	
	}
	
	// Generate a new guid for use as the id when POSTing new items
	// - there should be a better / official way to do this in PHP
	// - do not use this method on a production system
	function NewGuid()
	{
		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}

	
	// -------------------------------------------------------
	// TEST all methods and show the outputs 
	// -------------------------------------------------------
	
	// Call the GET customers method and print the results
	function testGetCustomers() {
		echo "Starting test: testGetCustomers" . "<br />";
		echo "<br />";
		
		echo "-------------------------------------------------------------------------------------<br />";		
		echo "GET customers in XML format:" . "<br />";
		echo "<br />";
		$xml = getCustomers("xml");
		echo htmlentities($xml->asXML());
		echo "<br />";
		echo "<br />";
		
		echo "GET customers in XML format: example of looping through the customer list" . "<br />";
		foreach ($xml->Customer as $customer) {
			$code = $customer->CustomerCode;
			$name = $customer->CustomerName;
			echo "XML Customer: $code, $name<br />";
		}
				
		echo "<br />";
		echo "<br />";
		
		echo "-------------------------------------------------------------------------------------<br />";		
		echo "GET customers in JSON format:" . "<br />";
		echo "<br />";
		$json = getCustomers("json");
		echo json_encode($json);
		echo "<br />";
		echo "<br />";		
		
		echo "GET customers in JSON format: example of looping through the customer list" . "<br />";
		foreach ($json->Items as $customer) {
			$code = $customer->CustomerCode;
			$name = $customer->CustomerName;
			echo "JSON Customer: $code, $name<br />";
		}
		
		echo "<br />";
		echo "<br />";
		echo "End of test: testGetCustomers" . "<br />";
		echo "-------------------------------------------------------------------------------------<br />";		
		
	}
		
	// Call the GET customers by name method and print the results
	function testGetCustomersByName() {
		echo "Starting test: testGetCustomersByName". "<br />";
		
		echo "-------------------------------------------------------------------------------------<br />";
		echo "GET customers by name in XML format:";
		$xml = getCustomersByName("ACE", "xml");
		echo htmlentities($xml->asXML());
		echo "<br />";
		echo "<br />";
		
		echo "GET customers in XML format: example of looping through the customer list" . "<br />";
		foreach ($xml->Customer as $customer) {
			$code = $customer->CustomerCode;
			$name = $customer->CustomerName;
			echo "XML Customer: $code, $name<br />";
		}
		
		echo "-------------------------------------------------------------------------------------<br />";
		echo "GET customers by name in JSON format:";
		$json = getCustomersByName("ACE", "json");
		echo json_encode($json);
		echo "<br />";
		echo "<br />";		
		
		echo "GET customers in JSON format: example of looping through the customer list" . "<br />";
		foreach ($json->Items as $customer) {
			$code = $customer->CustomerCode;
			$name = $customer->CustomerName;
			echo "JSON Customer: $code, $name<br />";
		}
		echo "<br />";
		echo "<br />";
		echo "End of test: testGetCustomersByName". "<br />";
		echo "-------------------------------------------------------------------------------------<br />";
		
	}
	
	// Call the POST new customers method using json and print the results
	function testPostNewCustomerJson() {
		
		echo "Starting test: testPostNewCustomerJson" . "<br />";
						
		echo "-------------------------------------------------------------------------------------<br />";
		echo "POST new customer in JSON format:" . "<br />";
		$guid = NewGuid();
		echo "New GUID = $guid";
		echo "<br />";
		
		$customer = new stdClass();
		$customer->Guid = "$guid";
		$customer->CustomerCode = "PHP-$guid";
		$customer->CustomerName = "New customer PHP-$guid";
		$customer->Notes = "Customer $guid added via json POST";
		
		echo "Input data:" . "<br />";
		echo json_encode($customer);
		$jsonPost = postCustomer($customer, "json");
		echo "<br />";
		echo "Output data:" . "<br />";
		echo json_encode($jsonPost);
		echo "<br />";
		
		echo "-------------------------------------------------------------------------------------<br />";
		echo "End of test: testPostNewCustomerJson" . "<br />";
		
	}
	
	// Call the POST updated an existing customer method and print the results
	function testPostUpdateCustomer() { 
		echo "Starting test: testPostUpdateCustomer" . "<br />"; 
		
		echo "-------------------------------------------------------------------------------------<br />"; 
		echo "POST update customer in XML format:" . "<br />"; 
		$xml = getCustomers("xml"); 
		$customer = $xml->Customer[0]; 
		$customer->Notes = "Customer updated via xml POST"; 
		echo "Input data:" . "<br />"; 
		echo htmlentities($customer->asXML()); 
		echo "<br />"; 
		echo "Input id:" . "<br />"; 
		echo $customer->Guid; 
		echo "<br />"; 
		echo "<br />"; 
		
		$xmlPost = postCustomer($customer, "xml", $customer->Guid); 
		echo "Output data:" . "<br />"; 
		echo htmlentities($xmlPost->asXML()); 
		
		/*
		echo "-------------------------------------------------------------------------------------<br />"; 
		echo "POST update customer in JSON format:" . "<br />"; 
		
		$json = getCustomers("json"); 
		$customer = $json->Items[0]; 
	     
		$customer->Notes = "Customer updated via json POST"; 
		echo "Input data:" . "<br />"; 
		echo json_encode($customer); 
		$jsonPost = postCustomer($customer, "json"); 
		echo "Output data:" . "<br />"; 
		echo json_encode($jsonPost); 
		*/
		
		echo "-------------------------------------------------------------------------------------<br />"; 
		echo "End of test: testPostUpdateCustomer" . "<br />"; 
		
	    } 

		
	// Call the POST new purchase method using json and print the results
	// NOTE - Not finished yet, has a problem with sending the correct date format.
	// NOTE - Post purchases in XML instead
	function testPostNewPurchase() {
		
		die("Not implemented");
		
		echo "Starting test: testPostNewPurchase" . "<br />";
						
		echo "-------------------------------------------------------------------------------------<br />";
		echo "POST new purchase in JSON format:" . "<br />";
		$guid = NewGuid();
		echo "New GUID = $guid";
		echo "<br />";
		
		// $date = "\/Date(1331550000000)\/";
		// // create all the sub objects
		// $supplier = new stdClass();
		// $supplier->SupplierCode = "AUR001";
		// $currency = new stdClass();
		// $currency->CurrencyCode = "NZD";
		// $warehouse = new stdClass();
		// $warehouse->WarehouseCode = "W1";
		// $tax = new stdClass();
		// $tax->TaxCode = "GST";

		// $purchase = new stdClass();
		// $purchase->Guid = "$guid";
		// $purchase->OrderNumber = substr($guid,0,15);
		// $purchase->RequiredDate = "$date";
		// $purchase->Supplier = $supplier;
		// $purchase->Currency = $currency;
		// $purchase->Warehouse = $warehouse;
		// $purchase->Tax = $tax;
		// $purchase->Comments = "Purchase $guid added via json POST";
		
		// echo "Input data:" . "<br />";
		// echo json_encode($purchase);
		// $jsonPost = postPurchaseOrder($purchase, "json");
		// echo "<br />";
		// echo "Output data:" . "<br />";
		// echo json_encode($jsonPost);
		// echo "<br />";
		
		echo "-------------------------------------------------------------------------------------<br />";
		echo "End of test: testPostNewPurchase" . "<br />";
		
	}	  

	// Call the POST new purchase method using xml and print the results
	function testPostNewCustomerXml() {
		echo "Starting test: testPostNewCustomerXml" . "<br />";
						
		echo "-------------------------------------------------------------------------------------<br />";
		echo "POST new customer in XML format:" . "<br />";
		$guid = NewGuid();
		echo "New GUID = $guid";
		echo "<br />";
		
		// creating an xml object in PHP:		
		$customer = new simpleXMLElement('<Customer />'); 
		
		// set all the properties of the customer
		// use simple xml, not stdClass
		$customer->Guid = "$guid";
		$customer->CustomerCode = "XML $guid";
		$customer->CustomerName = "New customer from XML $guid";
		
		echo "Input data:" . "<br />"; 
		echo htmlentities($customer->asXML()); 
		echo "<br />"; 
		echo "Input id:" . "<br />"; 
		echo $customer->Guid; 
		echo "<br />"; 
		echo "<br />"; 
		
		$xmlPost = postCustomer($customer, "xml", $customer->Guid); 
		echo "Output data:" . "<br />"; 
		echo htmlentities($xmlPost->asXML()); 
		
	}
	
	// Call the POST new purchase method using xml and print the results
	function testPostNewPurchaseXml() {
	
		echo "Starting test: testPostNewPurchaseXml" . "<br />";
						
		echo "-------------------------------------------------------------------------------------<br />";
		echo "POST new purchase in XML format:" . "<br />";
		$guid = NewGuid();
		echo "New GUID = $guid";
		echo "<br />";
		 
		$date = date('Y-m-d');
		$taxRate = 0.15;
		$taxCode = "G.S.T.";

		// creating an xml object in PHP:
		$purchase = new simpleXMLElement('<PurchaseOrder />'); 
		
		// set all the properties of the purchase
		// use simple xml, not stdClass
		$purchase->Guid = "$guid";
		$purchase->OrderNumber = substr($guid,0,15);
		$purchase->RequiredDate = $date;
		
		$purchase->Supplier->SupplierCode = "AUR001";
		$purchase->Currency->CurrencyCode = "NZD";
		$purchase->Warehouse->WarehouseCode = "W1";
		$purchase->Tax->TaxCode = $taxCode;
		
		$lines = $purchase->addChild('PurchaseOrderLines');
		addPurchaseLineXml($lines, 1, 'ANIMAL', 5, 10, $taxRate);
		addPurchaseLineXml($lines, 2, 'BISCUIT', 10, 2, $taxRate);
		addPurchaseLineXml($lines, 3, 'CANDY', 1, 25, $taxRate);
				
		$purchase->SubTotal = 95.00;
		$purchase->TaxTotal = 14.25;
		$purchase->Total = 109.25;
		
		$purchase->Comments = "Purchase $guid added via xml POST";
		
		echo "Input data:" . "<br />"; 
		echo htmlentities($purchase->asXML()); 
		echo "<br />"; 
		echo "Input id:" . "<br />"; 
		echo $purchase->Guid; 
		echo "<br />"; 
		echo "<br />"; 
		
		$xmlPost = postPurchaseOrder($purchase, "xml", $purchase->Guid); 
		echo "Output data:" . "<br />"; 
		echo htmlentities($xmlPost->asXML()); 
				
	}
	
	// Create a purchase order line XML object
	function addPurchaseLineXml($lines, $lineNumber, $productCode, $qty, $price, $taxRate) {
	
		$line = $lines->addChild('PurchaseOrderLine');
		$line->addChild('LineNumber', $lineNumber);
		$line->addChild('Guid', NewGuid());
		$product = $line->addChild('Product');
		$product->addChild('ProductCode', $productCode);
		$line->addChild('OrderQuantity', $qty);
		$line->addChild('UnitPrice', $price);
		$line->addChild('LineTotal', $qty * $price);		
		$line->addChild('LineTax', ($qty * $price * $taxRate) );		
		$tax =$product->addChild('Tax');
		$tax->addChild('TaxRate', $taxRate);	
		
	}

	function testPostNewSaleInvoiceXml() {
	
		echo "Starting test: testPostNewSaleInvoiceXml" . "<br />";
						
		echo "-------------------------------------------------------------------------------------<br />";
		echo "POST new sale invoice in XML format:" . "<br />";
		$guid = NewGuid();
		echo "New GUID = $guid";
		echo "<br />";
		
		$taxRate = 0.15;
		$taxCode = "G.S.T.";

		// creating an xml object in PHP:
		$sale = new simpleXMLElement('<SalesInvoice />'); 
		
		// set all the properties of the purchase
		// use simple xml, not stdClass
		$sale->Guid = "$guid";
		$sale->OrderNumber = substr($guid,0,15);
		$sale->OrderStatus = 'Completed';
		$sale->Customer->CustomerCode = 6;
		
		$sale->Currency->CurrencyCode = "NZD";
		$sale->Warehouse->WarehouseCode = "W1";
		$sale->Tax->TaxCode = $taxCode;
		
		$lines = $sale->addChild('SalesOrderLines');
		addSaleInvoiceLineXml($lines, 1, 'ANIMAL', 5, 10, $taxRate);
		addSaleInvoiceLineXml($lines, 2, 'BISCUIT', 10, 2, $taxRate);
		addSaleInvoiceLineXml($lines, 3, 'CANDY', 1, 25, $taxRate);
		
		$sale->SubTotal = 95.00;
		$sale->TaxTotal = 14.25;
		$sale->Total = 109.25;
		
		$sale->Comments = "Sale invoice $guid added via xml POST";
		
		echo "Input data:" . "<br />"; 
		echo htmlentities($sale->asXML()); 
		echo "<br />"; 
		echo "Input id:" . "<br />"; 
		echo $sale->Guid; 
		echo "<br />"; 
		echo "<br />"; 
		
		$xmlPost = postSaleInvoice($sale, "xml", $sale->Guid); 
		echo "Output data:" . "<br />"; 
		echo htmlentities($xmlPost->asXML()); 
				
	}

	function addSaleInvoiceLineXml($lines, $lineNumber, $productCode, $qty, $price, $taxRate) {
	
		$line = $lines->addChild('SalesInvoiceLine');
		$line->addChild('LineNumber', $lineNumber);
		$line->addChild('Guid', NewGuid());
		$product = $line->addChild('Product');
		$product->addChild('ProductCode', $productCode);
		$line->addChild('OrderQuantity', $qty);
		$line->addChild('UnitPrice', $price);
		$line->addChild('LineTotal', $qty * $price);		
		$line->addChild('LineTax', ($qty * $price * $taxRate) );		
		$tax =$product->addChild('Tax');
		$tax->addChild('TaxRate', $taxRate);	
		
	}
	
	testGetCustomers();
	testGetCustomersByName();
	testPostUpdateCustomer();
	testPostNewCustomerJson();
	// testPostNewPurchase();	 // not finished yet
	testPostNewCustomerXml();
	testPostNewPurchaseXml();
	testPostNewSaleInvoiceXml();

?>

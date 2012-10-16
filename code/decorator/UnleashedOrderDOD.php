<?

/**
 * Customer is required (You can specify only the GUID or CustomerCode and the Customer has to already exist)
 * DiscountRate is not required
 * Tax is required
 * SalesOrderLines is required (SubTotal has to match the sum LineTotal of the order lines and vice versa)
 * If TaxTotal is specified, it has to match the sum of LineTax of order lines and vice versa
 * If Total is specified, it has to match the sum of SubTotal + TaxTotal and vice versa
 * --- SalesInvoiceLines ---
 * You can create new products by just entering a product code (it'll auto save the default purchase price as line Unit price and set GUID to 00000000-0000-0000-0000-000000000000)
 * Can you specifiy the details of all new product here ?
 * GUID is optional
 * Product is required, can not be null and can be identified by productCode or GUID just like Customer
 * DueDate is not required
 * DiscountRate is not required
 * OrderQuantity, UnitPrice and LineTotal have to match
 * LineTax is required if you have TaxTotal field in order
 * BC fields are not required
 * Note : POST order with XML : JSON does not work
 *
 * OrderStatus is always required Add/Update
 */
class UnleashedOrderDOD extends UnleashedObjectDOD {
	
	static $u_class = 'SalesInvoices';
	static $unique_fields = array('OrderNumber', 'ID');

	static $post_format = 'xml';

	protected function onAfterWriteStart() {
		if($this->owner->IsSubmitted()) {
			parent::onAfterWriteStart();
		}
	}

	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		if($sync) {
			/*$status = $this->owner->getCustomerStatus(false);
			if(empty($status)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Status');
			}*/

			$member = $this->owner->Member();
			if($member->exists()) {
				$sync = $member->synchroniseUDatabase();
				if($sync) {
					$sync = $member->updateUDatabase();
				}
				if(! $sync) {
					return $this->notifyError('SS_FIELDS_MISSING', 'Member Validation');
				}
			}
			else {
				return $this->notifyError('SS_FIELDS_MISSING', 'Member');
			}

			$currency = $this->getUCurrency();
			if(empty($currency)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Currency');
			}

			return true;
		}
	}

	/**
	 * Code generated for XML
     */
	function getUFields() {
		$order = $this->owner;
		$fields = array(
			'OrderDate' => str_replace(' ', 'T', $order->Created), // XSD format
			// QuoteExpiryDate
			// RequiredDate
			'OrderStatus' => 'Parked', // Whatever we enter, it's always Parked. Used to be $order->getCustomerStatus(false),
			'Customer' => $order->Member()->getUFieldsForOrder(),
			// CustomerRef
			'Comments' => $order->CustomerOrderNote,
			// Warehouse
			// ReceivedDate
			'Currency' => array('CurrencyCode' => $this->getUCurrency()),
			// ExchangeRate
			// DiscountRate
			// 'Tax' => ,
			// 'TaxRate' => ,
			'SubTotal' => $order->SubTotal(),
			// 'TaxTotal' => ,
			'Total' => $order->Total()
		);
		if($order->CanHaveShippingAddress()) {
			$address = $order->ShippingAddress();
			$prefix = 'Shipping';
			if(! $address->exists()) {
				$address = $order->BillingAddress();
				$prefix = '';
			}
			$fields['DeliveryName'] = implode(' ', array($address->{"{$prefix}Prefix"}, $address->{"{$prefix}FirstName"}, $address->{"{$prefix}Surname"}));
			$fields['DeliveryStreetAddress'] = $address->{"{$prefix}Address"};
			$fields['DeliverySuburb'] = $address->{"{$prefix}Address2"};
			$fields['DeliveryCity'] = $address->{"{$prefix}City"};
			$fields['DeliveryCountry'] = $address->{"get{$prefix}FullCountryName"}();
			$fields['DeliveryPostCode'] = $address->{"{$prefix}PostalCode"};
		}
		return $fields;
	}

	function getUCurrency() {
		$currency = $this->owner->CurrencyUsed();
		if($currency->exists() && ! empty($currency->Code)) {
			return $currency->Code;
		}
		return Payment::site_currency();
	}
}
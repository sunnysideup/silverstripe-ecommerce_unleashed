<?

/**
 * Customer is required (You can specify only the GUID or CustomerCode and the Customer has to already exist)
 * DiscountRate is not required
 * Tax is required. The TaxCode field which is supposed to be unique is not actually : Australia and New Zealand have the same GST code.
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

	static $u_tax_guid;
	static $attribute_tax_class;
	static $exclude_attribute_classes = array();

	protected function onAfterWriteStart() {
		if($this->owner->IsPaid() && ! $this->owner->GUID) {
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
					return $this->notifyError('SS_RELATION_INVALID', 'Member');
				}
			}
			else {
				return $this->notifyError('SS_FIELDS_MISSING', 'Member');
			}

			// Currency is not required to POST but it should be
			$currency = $this->getUCurrency();
			if(empty($currency)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Currency');
			}

			$tax = $this->getUTax();
			if(! $tax) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Unleashed Tax');
			}

			$attributes = $this->owner->Attributes();
			foreach($attributes as $attribute) {
				if($attribute->ClassName != self::$attribute_tax_class) {
					if($attribute->CalculatedTotal != 0 || ! in_array($attribute->ClassName, self::$exclude_attribute_classes)) { // Only exclude them if CalculatedTotal equals 0
						// Precondition : Only order items
						$buyable = $attribute->Buyable(true);
						$extensions = array_keys($buyable->getExtensionInstances());
						foreach($extensions as $extension) {
							if(is_subclass_of($extension, 'UnleashedObjectDOD')) {
								$sync = $buyable->synchroniseUDatabase();
								if($sync) {
									$sync = $buyable->updateUDatabase();
								}
								if(! $sync) {
									return $this->notifyError('SS_RELATION_INVALID', "$buyable->ClassName #$buyable->ID");
								}
								break;
							}
						}
						// Todo : deal with other modifiers like delivery
					}
				}
			}

			return true;
		}
	}

	/**
	 * Code generated for XML
     */
	function getUFields() {
		$order = $this->owner;
		$tax = $this->getUTax();
		$customerRef = $order->Modifiers('OrderMarker');
		if($customerRef) {
			$customerRef = $customerRef->First();
			$customerRef = $customerRef->OrderFor;
		}
		$submissionLog = $order->SubmissionLog();
		$fields = array(
			'OrderDate' => str_replace(' ', 'T', $submissionLog->Created), // XSD format
			// QuoteExpiryDate
			// RequiredDate
			'OrderStatus' => 'Parked', // Whatever we enter, it's always Parked. Used to be $order->getCustomerStatus(false),
			'Customer' => $order->Member()->getUFieldsForOrder(),
			'CustomerRef' => $customerRef,
			'Comments' => $order->CustomerOrderNote,
			// Warehouse
			// ReceivedDate
			'Currency' => array('CurrencyCode' => $this->getUCurrency()),
			// ExchangeRate
			// DiscountRate
			'Tax' => array('Guid' => $tax['Guid']),
			// TaxRate
			// XeroTaxCode
			// SubTotal : Has to be equal to the sum of LineTotal of the order lines
			// TaxTotal : Has to be equal to the sum of LineTax of the order lines
			// Total : Has to be equal to the sum of SubTotal and Tax Total
			// TotalVolume
			// TotalWeight
			// BCSubTotal
			// BCTaxTotal
			// BCTotal
			// PaymentDueDate
			// SalesPerson
			// SalesOrderLines
			// LastModifiedOn
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
		unset($tax);
		if(self::$attribute_tax_class) { // We suppose it's GSTTaxModifier
			$tax = $order->Modifiers(self::$attribute_tax_class);
			if($tax) {
				$tax = $tax->First();
				$fields['TaxTotal'] = $tax->CalculatedTotal;
			}
		}
		$subTotal = $taxTotal = 0;
		$lines = array();
		$attributes = $this->owner->Attributes();
		foreach($attributes as $attribute) {
			if($attribute->ClassName != self::$attribute_tax_class) {
				if($attribute->CalculatedTotal != 0 || ! in_array($attribute->ClassName, self::$exclude_attribute_classes)) { // Only exclude them if CalculatedTotal equals 0
					// We suppose that there are only order items
					$buyable = $attribute->Buyable(true);
					$attributeFields = array(
						'LineNumber' => count($lines) + 1,
						'Product' => array('Guid' => $buyable->GUID),
						'OrderQuantity' => $attribute->Quantity,
						'UnitPrice' => $attribute->UnitPrice,
						'LineTotal' => $attribute->Total()
					);
					if(isset($tax)) { // We suppose it's GSTTaxModifier
						$attributeFields['TaxRate'] = $tax->CurrentRate;
						if($tax->TaxType == 'Inclusive') {
							foreach(array('UnitPrice', 'LineTotal') as $name) {
								$attributeFields[$name] = $attributeFields[$name] / (1 + $tax->CurrentRate);
							}
						}
						$attributeFields['LineTax'] = $attributeFields['LineTotal'] * $tax->CurrentRate;
						$taxTotal += $attributeFields['LineTax'];
					}
					$subTotal += $attributeFields['LineTotal'];
					$lines[] = $attributeFields;
					// Todo : deal with other modifiers like delivery
				}
			}
		}
		$fields['SalesOrderLines']['SalesInvoiceLine'] = $lines;
		$fields['SubTotal'] = $subTotal;
		if(isset($tax)) {
			$fields['TaxTotal'] = $taxTotal;
			if(bccomp($tax->CalculatedTotal, round($taxTotal, 2)) !== 0) {
				$errors[] = 'Tax';
			}
		}
		$fields['Total'] = $subTotal + $taxTotal;
		if(bccomp($order->Total, round($fields['Total'], 2)) !== 0) {
			$errors[] = 'Total';
		}
		if(isset($errors)) {
			return $this->notifyError('CALCULATION_INCORRECT', $errors);
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

	function getUTax() {
		if(self::$u_tax_guid) {
			$taxes = UnleashedAPI::get('Taxes');
			if($taxes) {
				foreach($taxes as $tax) {
					if($tax['Guid'] == self::$u_tax_guid) {
						return $tax;
					}
				}
			}
		}
	}
}
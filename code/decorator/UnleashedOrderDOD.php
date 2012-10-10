<?

class UnleashedOrderDOD extends UnleashedObjectDOD {
	
	static $u_class = 'SalesInvoices';
	static $unique_fields = array('OrderNumber', 'ID');

	static $update_after_write = false;
	
	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		if($sync) {
			$status = $this->owner->getCustomerStatus(false);
			if(empty($status)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Status');
			}

			$member = $this->owner->Member();
			if($member->exists()) {
				$sync = $member->synchroniseUDatabase();
				if(! $sync) {
					return $this->notifyError('SS_FIELDS_MISSING', 'Member Validation');
				}
			}
			else {
				return $this->notifyError('SS_FIELDS_MISSING', 'Member');
			}

			$currency = $this->owner->CurrencyUsed();
			if(! $currency->exists() || empty($currency->Code)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Currency');
			}

			return true;
		}
	}

	function getUFields() {
		$order = $this->owner;
		$fields = array(
			'OrderDate' => $order->Created,
			'OrderStatus' => $order->getCustomerStatus(false),
			'Customer' => $order->Member()->getUFields(),
			'Comments' => $order->CustomerOrderNote,
			//'ReceivedDate' => ,
			'Currency' => $order->CurrencyUsed()->Code,
			'DiscountRate' => 0,
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
			$fields['DeliveryCountry'] = $address->{"{$prefix}Country"};
			$fields['DeliveryPostCode'] = $address->{"{$prefix}PostalCode"};
		}
		return $fields;
	}
}
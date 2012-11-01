<?

class UnleashedMemberDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Customers';
	static $unique_fields = array('CustomerCode', 'ID');
	
	static $update_after_write = false;
	
	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		$orders = DataObject::get('Order', "MemberID = {$this->owner->ID}"); // $this->owner->Orders() does not work. Has EcommerceRole been added properly ?
		if($sync && $orders) {
			$name = $this->owner->Surname; // Company Name
			if(empty($name)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Company Name');
			}
			return true;
		}
	}

	function getUFields() {
		return array(
			'CustomerName' => $this->owner->Surname, // Company Name
			'Email' => $this->owner->Email,
			'Notes' => $this->owner->Notes
		);
	}

	function getUFieldsForOrder() {
		return array('Guid' => $this->owner->GUID);
	}
}
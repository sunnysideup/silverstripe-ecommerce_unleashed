<?

class UnleashedMemberDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Customers';
	static $unique_fields = array('CustomerCode', 'ID');
	
	static $update_after_write = false;
	
	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		$orders = $this->owner->Orders();
		if($sync && $orders->Count()) {
			$name = $this->owner->getName();
			if(empty($name)) {
				return $this->notifyError('SS_FIELDS_MISSING', 'Name');
			}
			return true;
		}
	}

	function getUFields() {
		return array(
			'CustomerName' => $this->owner->getName(),
			'Email' => $this->owner->Email,
			'Notes' => $this->owner->Notes
		);
	}
}
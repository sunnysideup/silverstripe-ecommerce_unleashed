<?

class UnleashedMemberDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Customers';
	static $unique_fields = array('CustomerCode', 'ID');
	/*
	static $errors = array(
		'NO_NAME' => array('SS Member #$ID Name Missing', "The member #\$ID does not have a 'name' value set which is required in order to create a new Unleashed object.")
	);
	*/
	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		$orders = $this->owner->Orders();
		if($sync && $orders->Count()) {
			$name = $this->owner->getName();
			if(empty($name)) {
				return $this->notifyError('SS_FIELD_MISSING', 'Name');
			}
			return true;
		}
	}

	function getUFields() {
		return array(
			'CustomerName' => $this->owner->getName(),
			'Email' => $this->owner->Email,
			'Notes' => "{$this->owner->Notes}"
		);
	}
}

/*class UnleashedMemberDOD extends DataObjectDecorator {
	
	static $uclass = 'Customers';

	function onAfterWrite() {
		$this->updateUDatabase();
	}

	function updateUDatabase() {
		$members = UnleashedObject::get(self::$uclass, array('customerCode' => $this->owner->ID));
		$uID = null;
		foreach($members as $member) {
			if($member['CustomerCode'] == $this->owner->ID) {
				$uID = $member['Guid'];
			}
		}
		$fields = array(
			'CustomerName' => $this->owner->getName(),
			'Email' => $this->owner->Email,
			'Notes' => $this->owner->Notes
		);
		if(! $uID) {
			$fields['CustomerCode'] = $this->owner->ID;
		}
		UnleashedObject::post(self::$uclass, $fields, $uID);
	}
}*/
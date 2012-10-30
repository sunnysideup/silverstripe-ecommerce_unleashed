<?

abstract class UnleashedObjectDOD extends DataObjectDecorator {

	static $u_class;
	static $unique_fields; // array(Unleashed Unique Field, SS Unique Field)

	static $guid_format = 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';
	static $guid_format_separator = '-';

	static $update_after_write = true;
	static $post_format = 'json';

	function extraStatics() {
		$length = strlen(self::$guid_format);
		return array('db' => array('GUID' => "Varchar($length)"));
	}

	function updateCMSFields(FieldSet &$fields) {
		$fields->removeByName('GUID');
	}

	/**
	 * Syncronisation call for @see SiteTree only when published
	 */
	function onAfterPublish() {
		if(is_a($this->owner, 'SiteTree')) {
			$this->onAfterWriteStart();
		}
	}

	/**
	 * Syncronisation call for @see DataObject which are not @see SiteTree
	 */
	function onAfterWrite() {
		if(! is_a($this->owner, 'SiteTree')) {
			$this->onAfterWriteStart();
		}
	}

	protected function onAfterWriteStart() {
		if($this->stat('update_after_write')) {
			$this->checkDODSettings();
			if($this->synchroniseUDatabase()) {
				$this->updateUDatabase();
			}
		}
	}

	function checkDODSettings() {
		if(! $this->stat('u_class')) {
			user_error("You must set the static variable 'u_class'.", E_USER_ERROR);
		}
		$uniqueFields = $this->stat('unique_fields');
		if($uniqueFields) {
			if(! is_array($uniqueFields) || count($uniqueFields) != 2) {
				user_error("You must set the static variable 'unique_fields' as an array of 2 strings.", E_USER_ERROR);
			}
			foreach($uniqueFields as $field) {
				if(! is_string($field)) {
					user_error("You must set the static variable 'unique_fields' as an array of 2 strings.", E_USER_ERROR);
				}
			}
		}
	}

	function synchroniseUDatabase() {return true;}

	function updateUDatabase() {
		$fields = $this->getUFields(); // Can return false if there is an error in the total, subtotal, taxtotal calculations
		if($fields) {
			$format = $this->stat('post_format');
			if($this->owner->GUID) { // uObject already created
				$uObject = $this->getUObjectByGUID();
				if(! $uObject) { // uObject has been deleted
					return $this->notifyError('U_OBJECT_DELETED');
				}
			}
			else {
				list($uField, $ssField) = $this->stat('unique_fields');
				if($uField) {
					if($this->owner->$ssField) {
						$uObject = $this->getUObjectByUniqueField();
						if($uObject) { // A uObject with the same ss code already exists so we can not add a new uObject with the same code
							return $this->notifyError('U_OBJECT_DUPLICATE', $ssField);
						}
					}
					else { // uObject can not be added because the unique field value is missing
						return $this->notifyError('SS_FIELDS_MISSING', $ssField);
					}
					$fields[$uField] = $this->owner->$ssField;
				}
				$newGUID = $this->owner->GUID = $this->createGUID();
				if($format == 'xml') {
					$fields['Guid'] = $newGUID;
				}
			}
			$uObject = UnleashedAPI::post($this->stat('u_class'), $this->owner->GUID, $fields, $format);
			if(! $uObject) { // The POST query failed
				return $this->notifyError('POST');
			}
			else if(isset($newGUID)) { // DO NOT USE isChanged('GUID') function to avoid infinite write loop calls
				$function = is_a($this->owner, 'SiteTree') ? 'doPublish' : 'write';
				$this->owner->$function();
			}
			return true;
		}
	}

	function createGUID() {
		$parts = explode(self::$guid_format_separator, self::$guid_format);
		while(! isset($guid) || $uObject) {
			$guid = array();
			foreach($parts as $part) {
				$guid[] = substr(str_shuffle('0123456789abcdef'), 0, strlen($part));
			}
			$guid = implode(self::$guid_format_separator, $guid);
			$uObject = UnleashedAPI::get_by_guid($this->stat('u_class'), $guid);
		}
		return $guid;
	}

	/**
	 * Precondition : GUID is set
	 */
	function getUObjectByGUID() {
		return UnleashedAPI::get_by_guid($this->stat('u_class'), $this->owner->GUID);
	}

	/**
	 * Precondition : hasUniqueField() is true and $this->owner->$ssField is set
	 */
	function getUObjectByUniqueField() {
		list($uField, $ssField) = $this->stat('unique_fields');
		$uObjects = UnleashedAPI::get($this->stat('u_class'), array(lcfirst($uField) => $this->owner->$ssField));
		if($uObjects) {
			foreach($uObjects as $uObject) {
				if($uObject[$uField] == $this->owner->$ssField) {
					return $uObject;
				}
			}
		}
	}

	function getUFields() {
		user_error('You must implement this function.', E_USER_ERROR);
	}

	function stat($name, $uncached = false) {
		return Object::get_static(($this->class ? $this->class : get_class($this)), $name, $uncached);
	}

	// Error Notifications

	static $errors = array(
		'U_OBJECT_DELETED' => array('Unleashed Object Of SS $ClassName #$ID Not Found', "The Unleashed object corresponding to the SS \$ClassName #\$ID had been previously created but can not be found anymore."),
		'U_OBJECT_DUPLICATE' => array('Unleashed Object Of SS $ClassName #$ID With Same $AndFieldNames Already Created', 'An Unleashed object with the same $AndFieldNames than the SS $ClassName #$ID has been found.<br/>Therefore, a new Unleashed object can not be created for SS $ClassName #$ID.'),
		'SS_FIELDS_MISSING' => array('SS $ClassName #$ID $AndFieldNames Missing', 'The SS $ClassName #$ID does not have a $OrFieldNames value set which is required in order to create a new Unleashed object.'),
		'POST' => array('SS $ClassName #$ID POST Transaction Failure', 'The POST transaction to update or create the Unleashed object of the SS $ClassName #$ID failed to complete successfully.'),
		'SS_RELATION_INVALID' => array('SS $ClassName #$ID $AndFieldNames Invalid', 'The $AndFieldNames of SS $ClassName #$ID is not valid.<br/>Therefore, a new Unleashed object can not be created for SS $ClassName #$ID.'),
		'CALCULATION_INCORRECT' => array('SS $ClassName #$ID $AndFieldNames Unleashed Recalculation(s) Incorrect', 'The recalculation(s) of $AndFieldNames of SS $ClassName #$ID do(es) not correspond to the $AndFieldNames value(s) recorded in the database.<br/>Therefore, a new Unleashed object can not be created for SS $ClassName #$ID.')
	);

	static $error_email_subject_prefix = 'SS - Unleashed Error : ';
	static $error_email_body_prefix = 'Hi Administrator,<br/><br/>';
	static $error_email_body_suffix = '<br/><br/>Regards';
	static $error_email_from;
	static $error_email_to;
	
	function notifyError($type, $fields = null) {
		list($subject, $body) = self::$errors[$type];
		
		$subject = self::$error_email_subject_prefix . $subject;
		$body = self::$error_email_body_prefix . $body . self::$error_email_body_suffix;

		if($fields) {
			if(! is_array($fields)) {
				$fields = array($fields);
			}
			$this->owner->AndFieldNames = implode(' and ', $fields);
			$this->owner->OrFieldNames = "'" . implode("' or '", $fields) . "'";
		}

		$parser = SSViewer::fromString($subject);
		$subject = $parser->process($this->owner);
		$parser = SSViewer::fromString($body);
		$body = $parser->process($this->owner);

		$from = self::$error_email_from;
		if(! $from) {
			$from = Email::getAdminEmail();
		}
		$to = self::$error_email_to;
		if(! $to) {
			$to = Email::getAdminEmail();
		}

		$email = new Email($from, $to, $subject, $body);
		$email->send();
	}
}
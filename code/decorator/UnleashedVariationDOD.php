<?

class UnleashedVariationDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Products';
	static $unique_fields = array('ProductCode', 'InternalItemID');

	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		if($sync) {
			if(empty($this->owner->Description)) {
				return $this->notifyError('SS_FIELD_MISSING', 'Description');
			}
			return true;
		}
	}

	function getUFields() {
		$product = $this->owner->Product();
		$fields = $product->getUFields();
		$fields['ProductDescription'] = $this->owner->Description;
		$fields['DefaultSellPrice'] = $this->owner->Price;

		// Attributes

		$attributeValues = $this->owner->AttributeValuesSorted();
		if($attributeValues->Count() > 0) {
			foreach($attributeValues as $attributeValue) {
				$attributeType = $attributeValue->ProductAttributeType();
				$attributes[] = "$attributeType->Name : $attributeValue->Value";
			}
			$fields['Notes'] .= "\n\n-- Attributes --\n\n" . implode("\n", $attributes);
		}
		return $fields;
	}
}
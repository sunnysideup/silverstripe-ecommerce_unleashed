<?
/**
 * Update notify error to deal with more than 1 field missing
 */
class UnleashedVariationDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Products';
	static $unique_fields = array('ProductCode', 'InternalItemID');

	static $alternative_description = true;

	function synchroniseUDatabase() {
		$sync = parent::synchroniseUDatabase();
		if($sync) {
			$description = $this->getUDescription();
			if(empty($description)) {
				$names[] = 'Description';
				if(self::$alternative_description) {
					$names[] = 'Alternative Description';
				}
				return $this->notifyError('SS_FIELDS_MISSING', $names);
			}
			return true;
		}
	}

	function getUFields() {
		$product = $this->owner->Product();
		$fields = $product->getUFields();
		$fields['ProductDescription'] = $this->getUDescription();
		$fields['DefaultSellPrice'] = $this->owner->Price;

		$attributes = $this->getAttributesList();
		if($attributes) {
			$fields['Notes'] .= "\n\n-- Attributes --\n\n" . implode("\n", $attributes);
		}

		return $fields;
	}

	function getUDescription() {
		$description = $this->owner->Description;
		if(empty($description) && self::$alternative_description) {
			$description = $this->alternativeDescription();
		}
		return $description;
	}

	function alternativeDescription() {
		$product = $this->owner->Product();
		$description = $product->Title;

		$attributes = $this->getAttributesList();
		if($attributes) {
			$description .= ' (' . implode(', ', $attributes) . ')';
		}

		return $description;
	}

	function getAttributesList() {
		$attributeValues = $this->owner->AttributeValuesSorted();
		if($attributeValues->Count() > 0) {
			foreach($attributeValues as $attributeValue) {
				$attributeType = $attributeValue->Type();
				$attributes[] = "$attributeType->Name : $attributeValue->Value";
			}
			return $attributes;
		}
	}
}
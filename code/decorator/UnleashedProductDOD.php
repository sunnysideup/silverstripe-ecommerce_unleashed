<?

class UnleashedProductDOD extends UnleashedObjectDOD {
	
	static $u_class = 'Products';
	static $ss_unique_field = 'InternalItemID';
	static $u_unique_field = 'ProductCode';

	function canUpdateUDatabase() {
		return parent::canUpdateUDatabase() && ! (Object::has_extension('Product', 'ProductWithVariationDecorator') && $this->owner->HasVariations());
	}

	function getUFields() {
		return array(
			'ProductDescription' => $this->owner->Title,
			'Weight' => $this->owner->Weight,
			'UnitOfMeasure' => $this->owner->Quantifier,
			'DefaultSellPrice' => $this->owner->Price,
			'Notes' => Convert::html2raw($this->owner->Content)
		);
	}
}

/*class UnleashedProductDOD extends DataObjectDecorator {
	
	static $uclass = 'Products';

	function onAfterWrite() {
		$this->updateUDatabase();
	}

	function updateUDatabase() {
		if(! (Object::has_extension('Product', 'ProductWithVariationDecorator') && $this->owner->HasVariations())) {
			if($this->owner->InternalItemID) {
				$products = UnleashedObject::get(self::$uclass, array('productCode' => $this->owner->InternalItemID));
				$uID = null;
				foreach($products as $product) {
					if($product['ProductCode'] == $this->owner->InternalItemID) {
						$uID = $product['Guid'];
					}
				}
				$fields = array(
					'ProductDescription' => $this->owner->Title,
					'Weight' => $this->owner->Weight,
					'UnitOfMeasure' => $this->owner->Quantifier,
					'DefaultSellPrice' => $this->owner->Price,
					'Notes' => Convert::html2raw($this->owner->Content)
				);
				if(! $uID) {
					$fields['ProductCode'] = $this->owner->InternalItemID;
				}
				UnleashedObject::post(self::$uclass, $fields, $uID);
			}
		}
	}
}*/
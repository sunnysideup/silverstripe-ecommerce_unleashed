<?php

class UnleashedProductDOD extends UnleashedObjectDOD
{
    public static $u_class = 'Products';
    public static $unique_fields = array('ProductCode', 'InternalItemID');
    
    public function synchroniseUDatabase()
    {
        $sync = parent::synchroniseUDatabase();
        $sync = $sync && ! (Object::has_extension('Product', 'ProductWithVariationDecorator') && $this->owner->HasVariations());
        if ($sync) {
            if (empty($this->owner->Title)) {
                return $this->notifyError('SS_FIELDS_MISSING', 'Title');
            }
            return true;
        }
    }

    public function getUFields()
    {
        return array(
            'ProductDescription' => $this->owner->Title,
            'Weight' => $this->owner->Weight,
            'UnitOfMeasure' => $this->owner->Quantifier,
            'DefaultSellPrice' => $this->owner->Price,
            'Notes' => Convert::html2raw($this->owner->Content)
        );
    }
}

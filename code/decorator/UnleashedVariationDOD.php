<?php

/**
 * @todo Fix the problem where the values come from the cached object instead of the values from the DB because there is an error
 *       occuring when you enter a more than 30 characters long Internal Item ID.
 */
class UnleashedVariationDOD extends UnleashedObjectDOD
{

    public static $u_class = 'Products';
    public static $unique_fields = array('ProductCode', 'InternalItemID');

    public static $alternative_description = true;

    public function synchroniseUDatabase()
    {
        $sync = parent::synchroniseUDatabase();
        if ($sync) {
            $description = $this->getUDescription();
            if (empty($description)) {
                $names[] = 'Description';
                if (self::$alternative_description) {
                    $names[] = 'Alternative Description';
                }
                return $this->notifyError('SS_FIELDS_MISSING', $names);
            }
            return true;
        }
    }

    public function getUFields()
    {
        $product = $this->owner->Product();
        $fields = $product->getUFields();
        $fields['ProductDescription'] = $this->getUDescription();
        $fields['DefaultSellPrice'] = $this->owner->Price;

        $attributes = $this->getAttributesList();
        if ($attributes) {
            $fields['Notes'] .= "\n\n-- Attributes --\n\n" . implode("\n", $attributes);
        }

        return $fields;
    }

    public function getUDescription()
    {
        $description = $this->owner->Description;
        if (empty($description) && self::$alternative_description) {
            $description = $this->alternativeDescription();
        }
        return $description;
    }

    public function alternativeDescription()
    {
        $product = $this->owner->Product();
        $description = $product->Title;

        $attributes = $this->getAttributesList();
        if ($attributes) {
            $description .= ' (' . implode(', ', $attributes) . ')';
        }

        return $description;
    }

    public function getAttributesList()
    {
        $attributeValues = $this->owner->AttributeValuesSorted();
        if ($attributeValues->Count() > 0) {
            $attributes = array();
            foreach ($attributeValues as $attributeValue) {
                if (is_object($attributeValue)) {
                    $attributeType = $attributeValue->Type();
                    $attributes[] = "$attributeType->Name : $attributeValue->Value";
                }
            }
            return $attributes;
        }
    }
}

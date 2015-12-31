<?php

class UnleashedTask extends BuildTask
{

    public function getTitle()
    {
        return 'Unleashed Synchronisation Summary';
    }

    public function getDescription()
    {
        return 'Shows the oustanding objects to be synchronised with Unleashed';
    }

    public function run($request)
    {
        $this->checkProducts();
        $this->checkVariations();
    }
    
    public function checkProducts()
    {
        $products = DataObject::get('Product', 'GUID IS NULL', 'Created ASC');
        $errors = 0;
        if ($products) {
            foreach ($products as $product) {
                $variations = $product->Variations();
                if ($variations->Count() == 0) {
                    $errors++;
                    DB::alteration_message("Product #$product->ID '$product->Title' is not synchronised with Unleashed", 'error');
                }
            }
        }
        if ($errors) {
            DB::alteration_message("$errors products have not been synchronised with Unleashed", 'error');
        } else {
            DB::alteration_message('All products have been synchronised with Unleashed', 'created');
        }
    }

    public function checkVariations()
    {
        $variations = DataObject::get('ProductVariation', 'GUID IS NULL', 'Created ASC');
        $errors = 0;
        if ($variations) {
            foreach ($variations as $variation) {
                $product = $variation->Product();
                if ($product->exists()) {
                    $errors++;
                    DB::alteration_message("Variation #$variation->ID '{$variation->getUDescription()}' is not synchronised with Unleashed", 'error');
                }
            }
        }
        if ($errors) {
            DB::alteration_message("$errors variations have not been synchronised with Unleashed", 'error');
        } else {
            DB::alteration_message('All variations have been synchronised with Unleashed', 'created');
        }
    }
}

class UnleashedTask_AdminEXT extends Extension
{

    public static $allowed_actions = array('unleashedTask' => true);

    public function updateEcommerceDevMenuConfig($tasks)
    {
        $tasks[] = 'unleashedTask';
        return $tasks;
    }
    
    public function unleashedTask($request)
    {
        $task = new UnleashedTask($request);
        $task->run($request);
        $this->owner->displayCompletionMessage($task);
    }
}

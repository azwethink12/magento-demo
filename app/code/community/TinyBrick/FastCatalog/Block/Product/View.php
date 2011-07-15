<?php
/**
 * TinyBrick Commercial Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the TinyBrick Commercial Extension License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://store.delorumcommerce.com/license/commercial-extension
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@tinybrick.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this package to newer
 * versions in the future. 
 *
 * @category   TinyBrick
 * @package    TinyBrick_FastCatalog
 * @copyright  Copyright (c) 2010 TinyBrick Inc. LLC
 * @license    http://store.delorumcommerce.com/license/commercial-extension
 */

class TinyBrick_FastCatalog_Block_Product_View extends Mage_Catalog_Block_Product_View
{
    public function getCacheKey() 
    {
        return md5("store_" . Mage::app()->getStore()->getId() . 'catalog_product_view_' . $this->getProduct()->getId() . '_' . $this->getTemplate() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode());
    }
    
    public function getCacheLifetime()
    {
    	if($messageBlock = $this->getLayout()->getBlock('messages')){
    		if(count($messageBlock->getMessageCollection())){
    			return null;
    		}
    	}
        return false;
    }
    
    public function getCacheTags()
    {
        $tags = array(Mage_Catalog_Model_Product::CACHE_TAG . '_' . $this->getProduct()->getId());
        
        if($this->getProduct()->getTypeId() == 'configurable'){
	        $used = $this->getProduct()->getTypeInstance()->getUsedProducts();
	        if(count($used)){
		        foreach ($used as $product){
		            $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $product->getId();
		        }
	        }
        }
        return $tags;
    }
}
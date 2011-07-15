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

class TinyBrick_FastCatalog_Block_Category_View extends Mage_Catalog_Block_Category_View
{
	
	public function getCacheKey()
	{
		return $this->_createUniqueKey();
	}
	
	public function getCacheTags()
	{
		$tags = array(Mage_Catalog_Model_Category::CACHE_TAG . "_" . $this->getCurrentCategory()->getId());
        if($child = $this->getChild('product_list')){
	        $productCollection = $child->getLoadedProductCollection();
	        if(count($productCollection)){
		        foreach ($productCollection as $product) {
		            $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $product->getId();
		        }
	        }
        	
        }
        return $tags;
	}
	
	public function getCacheLifetime()
	{
		return false;
	}
	
	protected function _createUniqueKey()
	{
		$catId = $this->getRequest()->getParam('id');
		$params = $this->getRequest()->getParams();
		if(!isset($params['limit'])){
			if(Mage::getSingleton('catalog/session')->hasData('limit_page')){
				$params['limit'] = Mage::getSingleton('catalog/session')->getLimitPage();
			}
		}
		unset($params['id']);
		ksort($params);
		$filters = "";
		foreach($params as $key=>$value){
			$filters .= "_" . $key . ":" . $value;
		}
		$cacheKey = "store_" . Mage::app()->getStore()->getId() . "_catalog_category_view_id_" . $catId . $filters . $this->getTemplate() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode();
		$cacheKey = md5($cacheKey);
		return $cacheKey;
	}
}
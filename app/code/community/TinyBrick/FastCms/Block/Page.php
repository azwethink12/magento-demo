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
 * @package    TinyBrick_FastCms
 * @copyright  Copyright (c) 2010 TinyBrick Inc. LLC
 * @license    http://store.delorumcommerce.com/license/commercial-extension
 */

class TinyBrick_FastCms_Block_Page extends Mage_Cms_Block_Page
{
	public function getCacheKey()
	{
		$key = "CMS_PAGE_" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		if(Mage::app()->getStore()->isCurrentlySecure()){
			$key = "SECURE_" . $key;
		}
		return $key;
	}
	
	public function getCacheTags()
	{
		return array(TinyBrick_FastCms_Model_Page::CACHE_TAG . "_" . (($page = $this->getPage())? $page->getId() : ''));
	}
	
	public function getCacheLifetime()
	{
		return false;
	}
	
	protected function _loadCache()
	{
		if (is_null($this->getCacheLifetime()) || !Mage::app()->useCache('cms_page')) {
            return false;
        }
        return Mage::app()->loadCache($this->getCacheKey());
	}
	
	protected function _saveCache($data)
	{
		if (is_null($this->getCacheLifetime()) || !Mage::app()->useCache('cms_page')) {
            return false;
        }
        Mage::app()->saveCache($data, $this->getCacheKey(), $this->getCacheTags(), $this->getCacheLifetime());
        return $this;
	}

}
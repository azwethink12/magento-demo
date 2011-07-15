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

class TinyBrick_FastCms_Block_Block extends Mage_Core_Block_Abstract
{
	protected $_block;
	
	public function getCacheKey()
	{
		$key = "CMS_BLOCK_" . $this->getBlockId();
		if(Mage::app()->getStore()->isCurrentlySecure()){
			$key = "SECURE_" . $key;
		}
		return $key;
	}
	
	public function getCacheTags()
	{
		return array(Mage_Cms_Model_Block::CACHE_TAG . "_" . (($block = $this->getBlock())? $block->getId() : ''));
	}
	
	public function getCacheLifetime()
	{
		return false;
	}
	
	public function getBlock()
	{
		if (!$this->_block) {
			if ($blockId = $this->getBlockId()) {
				$this->_block = Mage::getModel('cms/block')
	                ->setStoreId(Mage::app()->getStore()->getId())
	                ->load($blockId);
			}
		}
		return $this->_block;
	}
	
	protected function _loadCache()
	{
		if (is_null($this->getCacheLifetime()) || !Mage::app()->useCache('cms_block')) {
            return false;
        }
        return Mage::app()->loadCache($this->getCacheKey());
	}
	
	protected function _saveCache($data)
	{
		if (is_null($this->getCacheLifetime()) || !Mage::app()->useCache('cms_block')) {
            return false;
        }
        Mage::app()->saveCache($data, $this->getCacheKey(), $this->getCacheTags(), $this->getCacheLifetime());
        return $this;
	}
	
	protected function _toHtml()
    {
		if (!$this->_beforeToHtml()) {
			return '';
		}
        $html = '';
		if ($block = $this->getBlock()) {
			if (!$block->getIsActive()) {
                $html = '';
            } else {
                $content = $block->getContent();

                $processor = Mage::getModel('core/email_template_filter');
                $html = $processor->filter($content);
            }
		}
        return $html;
    }
}
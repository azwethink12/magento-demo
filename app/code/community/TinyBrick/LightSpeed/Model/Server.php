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
 * @package    TinyBrick_LightSpeed
 * @copyright  Copyright (c) 2010 TinyBrick Inc. LLC
 * @license    http://store.delorumcommerce.com/license/commercial-extension
 */

class TinyBrick_LightSpeed_Model_Server
{
	protected $_server;
	protected $_enabled 		= false;
	protected $_useCompression	= false;
	
	public function getServer()
	{
		if(!$this->_server){
			$type = Mage::getConfig()->getNode('lightspeed/cache/type');
			if ($type == "memcached"){
				$this->_server = Mage::getModel("lightspeed/server_memcache");
			}else{
				$this->_server = Mage::getModel("lightspeed/server_files");
			}
		}
		return $this->_server;
	}
	
	public function save($key, $data, $expires=0, array $tags=array())
	{
		return $this->getServer()->save($key, $data, $expires, $tags);
	}
	
	public function cleanByTag($tag)
	{
		return $this->getServer()->cleanByTag($tag);
	}
	
	public function clean($tags=array())
    {
	    if(!is_array($tags)){
        	$tags = array($tags);
	    }
	    $newTags = array();
	    if(count($tags)){
            foreach($tags as $tag){
                    $newTags[] = strtoupper($tag);
            }
	    }
	    return $this->getServer()->clean($newTags);
    }
}
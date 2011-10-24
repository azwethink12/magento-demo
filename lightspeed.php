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


// To enable debugging, uncomment the following
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
require_once 'app/Mage.php';
Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);

if(!PageCache::doYourThing()){
	include_once('index.php');
}

class PageCache
{
	static private $isCookieNew				= true;
	static private $sessionType				= '';
	static private $rawSession				= '';
	static private $session					= '';
	static private $sessionConfig			= array();
	static private $cacheEngine				= '';
	static private $cacheData				= array();
	static private $mysqlidatabase			= array();
	static private $pdodatabase				= array();
	static private $conditions				= array();	// loggedin, cart
	static private $initConditions			= false;
	static private $holeContent				= array();
	static private $request_path 			= '';
	static private $debugMode				= false;
	static private $multiCurrency			= false;
	static private $storeCode 				= false;
	static private $defaultCurrencyCode		= '';
	static private $config					= '';
	static private $tablePrefix				= '';
	
	public static function doYourThing()
	{
		try{
			self::prepareDebugger();
			self::verifyConfigurationExists();
			self::loadConfiguration();
			self::redirectAdmin();
			self::initCookie();
			self::renderCachedPage();
			return true;
		}catch(Exception $e){
			self::report("Error: {$e->getMessage()}", true);
			return false;
		}
	}

	public static function redirectAdmin()
	{
		// detect existance of 'admin' keyword and redirect immediately to index.php
		// todo, toss in some logic to allow custom admin url routes
		if (preg_match('/\/admin(\/|$)/', $_SERVER['REQUEST_URI'])) {
			throw new Exception("admin interface detected");
		}
	}

	public static function initCookie()
	{
		if(!isset($_COOKIE['frontend'])){
			self::report("first time visitor, I will be creating a cookie from here");
			// create the cookie so Magento doesn't fail
			self::buildCookie();
		}else{
			self::report("not a new visitor, using old cookie");
			self::$isCookieNew = false;
		}
	}
	
	public static function buildCookie()
	{
		require_once 'app/Mage.php';
		$request = new Zend_Controller_Request_Http();
		session_set_cookie_params(
			 self::getCookieLifetime()
			,self::getDefaultCookiePath()
			,$request->getHttpHost()
			,false
			,true
		);
		session_name('frontend');
		session_start();
	}
	
	public static function messageExists()
	{
		$message = false;
		if(!self::$isCookieNew){
			self::$rawSession = self::getRawSession();
			if(preg_match('/_messages.*?{[^}]*?Mage_Core_Model_Message_(Success|Error|Notice).*?}/s', self::$rawSession) > 0){
				$message = true;
			}
		}
		return $message;
	}

	public static function initConditions()
	{
		if(self::$initConditions){
			return;
		}
		// get the session_id from the cookie : $_COOKIE['frontend']
		if(!self::$isCookieNew){
			$session = self::getSession();
			// see if they are a logged in customer
			if(isset($session['customer_base'])){
				if(isset($session['customer_base']['id'])){
					// ensure they haven't logged out
					if((int)$session['customer_base']['id'] >= 1){
						self::$conditions[] = 'loggedin';
					}
				}
			}
			// see if they have started a cart
             if(isset($session['checkout'])){
                if(isset($session['core']['visitor_data']['quote_id']) && ($quoteId = $session['core']['visitor_data']['quote_id'])){
                    $sql = "SELECT COUNT(*) FROM ". self::getTableName('sales_flat_quote_item') ." WHERE quote_id = $quoteId";
					if(self::useMySqli()){
						//mysqli
						$rresult = mysqli_query(self::$mysqlidatabase, $sql);
						while($rrow = mysqli_fetch_array($rresult)){
							if((int)$rrow[0] >= 1){
								self::$conditions[] = 'cart';
							}
							break;
						}
					}else{
						//PDO
						foreach(self::$pdodatabase->query($sql) as $rrow) {
					        if((int)$rrow[0] >= 1){
								self::$conditions[] = 'cart';
							}
							break;
					    }
					}
                }
            }
			//See if they have added items to a compare
			if(isset($session['catalog'])){
				if(isset($session['catalog']['catalog_compare_items_count'])){
					if($session['catalog']['catalog_compare_items_count'] > 0){
						self::$conditions[] = 'compare';
					}
				}
			}
			
		}
		self::$initConditions = true;
	}
	
	public static function prepareSession()
	{
		if(!self::$session){
			self::$session = @self::unserializeSession(self::getRawSession());
			if (!self::$session) {
				self::report("unable to parse the session, generally this is because the session has expired");
			}
		}
	}
	
	public static function get($key)
	{
		switch(self::$cacheEngine){
			case 'memcached':
				return self::$cacheData['server']->get($key);
				break;
			case 'files':
				if($data = @file_get_contents(self::$cacheData['path'] . "/" . md5($key))){
					return unserialize($data);
				}
				break;
		}
		return false;
	}
	
	public static function getCachedPage()
	{
		$key = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'){
			$key = 'SECURE_' . $key;
		}
		$key = preg_replace('/(\?|&|&&)debug_front=1/s', '', $key);
		
		if(self::$multiCurrency){
			self::report("configuration set to use multi_currency");
			$key .= '_' . self::getCurrencyCode();
		}
		
		print_r ($_SERVER['SERVER_NAME']);
		exit;
		
		self::report("attempting to fetch url: $key");
		if($data = self::get($key)){
			if(self::messageExists()){
				self::report("a global message exists, we must not allow a cached page", true);
				return false;
			}
			if(isset($data[1]) && $data[1]){
				$disqualified = false;
				if($data[1] == '*'){ // auto disqualify when messages exist in the session
					self::report("disqualified because the disqualifier is *");
					$disqualified = true;
				}else{
					self::initConditions();
					$disqualifiers = explode(",", $data[1]);
					if($count = count($disqualifiers)){
						for($i=0; $i<$count; $i++){
							if(in_array($disqualifiers[$i], self::$conditions)){
								self::report("disqualified with {$disqualifiers[$i]}");
								$disqualified = true;
								break 1;
							}
						}
					}
				}
				if($disqualified){
					// handle dynamic content retrieval here
					if(isset($data[2]) && $data[2]){
						self::report("attempting to retrieve hole punched content from {$data[2]}");
						$_SERVER['REQUEST_URI'] = self::$request_path . "/" . $data[2];
						require_once 'app/Mage.php';
						ob_start();
						//Single Site
						Mage::run();
						//Multi-site test settings
						// Add a "case" statement for each new site
                        // switch($_SERVER['HTTP_HOST']) {
                        //   case 'www.site1.com':
                        //     Mage::run('site1', 'website');
                        //     break;
                        // 
                        //   case 'www.site2.com':
                        //     Mage::run('site2', 'website');
                        //     break;
                        // 
                        //   default:
                        //     Mage::run();
                        //     break;
						//End Multi-site Config
						$content = ob_get_clean();
						self::$holeContent = Zend_Json::decode($content);
						return self::fillNoCacheHoles($data[0]);
					}else{
						self::report("valid disqualifiers without hole punch content... bummer", true);
						return false;
					}
				}else{
					return $data[0];
				}
			}else{
				return $data[0];
			}
		}else{
			self::report("No match found in the cache store", true);
			return false;
		}
	}

	public static function getDefaultCookiePath()
	{
		$path = "/";
		try{
			$sql = "SELECT value FROM ". self::getTableName('core_config_data') ." WHERE path = 'web/cookie/cookie_path' AND scope = 'default' AND scope_id = 0";
			if(self::useMySqli()){
				//mysqli
				$result = mysqli_query(self::$mysqlidatabase, $sql);
				while($row = mysqli_fetch_array($result)){
					if(isset($row[0])){
						$path = $row[0];
					}
				}
			}else{
				//PDO
				foreach(self::$pdodatabase->query($sql) as $row) {
			        if(isset($row[0])){
						$path = $row[0];
					}
			    }
			}
		}catch(Exception $e){}
		
		return $path;
	}

	public static function getCurrencyCode()
	{
		$currencyCode = '';
		$session = self::getSession();
		if($session && isset($session[self::getStoreCode()])){
			self::report("found the session data for store code: " . self::getStoreCode());
			if(isset($session[self::getStoreCode()]['currency_code'])){
				self::report("found a currency code in the session: " + $session[self::getStoreCode()]['currency_code']);
				$currencyCode = $session[self::getStoreCode()]['currency_code'];
			}
		}
		if(!$currencyCode){
			self::report("defaulting to default currency code: " . self::getDefaultCurrencyCode());
			$currencyCode = self::getDefaultCurrencyCode();
		}
		return $currencyCode;
	}

	public static function getSession()
	{
		if (!self::$session) {
			self::prepareSession();
		}
		return self::$session;
	}

	public static function getRawSession()
	{
		if (!self::$rawSession) {
			switch(self::$sessionType){
				case 'db':
					$sql = "SELECT session_data FROM ". self::getTableName('core_session') ." WHERE session_id = '{$_COOKIE['frontend']}'";
					if(self::useMySqli()){
						//mysqli
						$result = mysqli_query(self::$mysqlidatabase, $sql);
						while($row = mysqli_fetch_array($result)){
							if(isset($row[0])){
								$path = $row[0];
							}
						}
						$result = mysqli_query(self::$sessionConfig['connection'], $sql);
						if(count($result)){
							while($row = mysqli_fetch_array($result)){
								return $row[0];	
							}
						}
					}else{
						//PDO
						foreach(self::$pdodatabase->query($sql) as $row) {
					        if(isset($row[0])){
								$path = $row[0];
							}
					    }
						$result = mysqli_query(self::$sessionConfig['connection'], $sql);
						foreach($result = self::$pdodatabase->query($sql) as $row) {
							if(count($result)){
								return $row[0];
							}
						}
					}
					break;
				case 'memcached':
					return self::$sessionConfig['server']->get($_COOKIE['frontend']);
					break;
				case 'files':
				default:
					return @file_get_contents(self::$sessionConfig['path'] . "/" . "sess_" . $_COOKIE['frontend']);
					break;
			}
		}
		return self::$rawSession;
	}
	
	public static function unserializeSession($data)
	{
		$result = false;
		if($data){
		    $vars = preg_split('/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff^|]*)\|/', $data,-1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		    $numElements = count($vars);
		    for($i=0; $numElements > $i && $vars[$i]; $i++) {
		        $result[$vars[$i]]=unserialize($vars[++$i]);
		    }
		}
	    return $result;
	}

	public static function fillNoCacheHoles($html)
	{
		return preg_replace_callback('/(\<!\-\- +nocache.+?\-\-\>).*?(\<!\-\- endnocache \-\-\>)/si', 'PageCache::replaceNoCacheBlocks', $html); 
	}
	
	public static function replaceNoCacheBlocks($matches)
	{
		// $matches[0] is the whole block
		// $matches[1] is the <!-- nocache -->
		// $matches[2] is the <!-- endnocache -->
		// print_r($matches);
		$key = self::getAttributeValue('key', $matches[1]);
		if(isset(self::$holeContent[$key])){
			return self::$holeContent[$key]; 
		}else{
			return $matches[0];
		}
	}
	
	public static function getAttributeValue($attribute, $html)
	{
		preg_match('/(\s*'.$attribute.'=\s*".*?")|(\s*'.$attribute.'=\s*\'.*?\')/', $html, $matches);
		
		if(count($matches)){
			$match = $matches[0];
			$match = preg_replace('/ +/', "", $match);
			$match = str_replace($attribute."=", "", $match);
			$match = str_replace('"', "", $match);
			return $match;
		}else{
			return false;
		}
	}
	
	public static function sanitizePage($page)
	{
		$page = preg_replace('/\<!\-\- +nocache.+?\-\-\>/si', "", $page);
		$page = preg_replace('/\<!\-\- endnocache \-\-\>/si', "", $page);
		return $page;
	}
	
	public static function getCookieLifetime()
	{
		$lifetime = 3600;
		try{
			$sql = "SELECT value FROM ". self::getTableName('core_config_data') ." WHERE path = 'web/cookie/cookie_lifetime' AND scope = 'default' AND scope_id = 0";
			if(self::useMySqli()){
				//mysqli
				$result = mysqli_query(self::$mysqlidatabase, $sql);
				while($row = mysqli_fetch_array($result)){
					if(isset($row[0])){
						$lifetime = (int) $row[0];
					}
				}
			}else{
				//PDO
				foreach(self::$pdodatabase->query($sql) as $row) {
			        if(isset($row[0])){
						$lifetime = (int) $row[0];
					}
			    }
			}
		}catch(Exception $e){}
		
		return $lifetime;
	}
	
	public static function report($message, $term=false)
	{
		if (self::$debugMode) {
			echo "$message<br />";
			if ($term) {
				exit;
			}
		}
	}
	
	public static function prepareDebugger()
	{
		if (isset($_GET['debug_front']) && $_GET['debug_front'] == '1') {
			self::$debugMode = true;
		}
	}
	
	public static function verifyConfigurationExists()
	{
		if(!file_exists('app/etc/local.xml')){
			throw new Exception('cannot find local.xml at app/etc/local.xml');
		}
	}
	
	public static function loadConfiguration()
	{
		$config = self::$config = simplexml_load_file('app/etc/local.xml');
		$nodeFound = false;
		foreach($config->children() as $child){
			if($child->getName() == 'lightspeed'){
				$nodeFound = true;
				foreach($child->children() as $child2){
					switch($child2->getName()){
						case 'global':
							self::report("found the global db node");
							if(self::useMySqli()){
								//mysqli
								self::$mysqlidatabase = mysqli_connect((string)$child2->connection->host, (string)$child2->connection->username, (string)$child2->connection->password);
								mysqli_select_db(self::$mysqlidatabase, (string)$child2->connection->dbname);
							}else{
								//pdo
								try {
								    self::$pdodatabase = new PDO('mysql:host='.(string)$child2->connection->host.';dbname='.(string)$child2->connection->dbname, (string)$child2->connection->username, (string)$child2->connection->password);
								} catch (PDOException $e) {}
							}
							
							self::$request_path = (string)$child2->request_path;
							self::$request_path = rtrim(trim(self::$request_path), '/');
							if($child2->multi_currency){
								self::$multiCurrency = (int) $child2->multi_currency;
							} 	
						break;
						case 'session':
							switch((string)$child2->type){
								case 'memcached':
									// self::report("Session store is memcached");
									if(!class_exists('Memcache')){
										throw new Exception('Memcache extension not installed, but configured for use in local.xml');
									}
									self::$sessionType = 'memcached';
									self::$sessionConfig['server'] = new Memcache();
									foreach($child2->servers->children() as $server){
										self::$sessionConfig['server']->addServer(
											 (string)$server->host
											,(int)$server->port
											,(bool)$server->persistant
										);
									}
									break;
								case 'db':
									// self::report("session store is db");
									self::$sessionType = 'db';
									self::$sessionConfig['connection'] = mysqli_connect((string)$child2->connection->host, (string)$child2->connection->username, (string)$child2->connection->password);
									mysqli_select_db(self::$sessionConfig['connection'], (string)$child2->connection->dbname);
									break;
								case 'files':
								default:
									// self::report("session store is files");
									self::$sessionType = 'files';
									self::$sessionConfig['path'] = (string) $child2->path;
									if(!self::$sessionConfig['path']){
										self::$sessionConfig['path'] = 'var/session';
									}
									break;
							}
							break;
						case 'cache':
							switch((string)$child2->type){
								case 'memcached':
									// self::report("cache engine is memcached");
									if(!class_exists('Memcache')){
										throw new Exception('Memcache extension not installed, but configured for use in local.xml');
									}
									self::$cacheEngine = 'memcached';
									self::$cacheData['server'] = new Memcache();
									foreach($child2->servers->children() as $server){
										self::$cacheData['server']->addServer(
											 (string)$server->host
											,(int)$server->port
											,(bool)$server->persistant
										);
									}
									break;
								case 'files':
								default:
									// self::report("cache engine is files");
									self::$cacheEngine = 'files';
									self::$cacheData['path'] = (string)$child2->path;
									if(!self::$cacheData['path']){
										self::$cacheData['path'] = 'var/lightspeed';
									}
									break;
							}
							break;
					}
				}
			}
		}
		
		if(!$nodeFound){
			throw new Exception("local.xml does not contain <lightspeed> node");
		}
	}
	
	public static function renderCachedPage()
	{
		if($page = self::getCachedPage()){
			self::report("success!, I'm about to spit out a cached page, look out.", true);
			self::prepareHeaders();
			echo self::sanitizePage($page);
		}else{
			throw new Exception("no cache matches at this url.");
		}
	}
	
	public static function prepareHeaders()
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, must-revalidate, no-store, post-check=0, pre-check=0");
	}
	
	public static function getStoreCode() 
	{
		if(!self::$storeCode){
			if(!self::getSession()) {
				self::report("session data is false, setting store code to: store_default");
				self::$storeCode = 'store_default';
			} else {
				foreach(array_keys(self::getSession()) as $_key) {
					if(substr($_key,0,5) == 'store') {
						self::$storeCode = $_key;
						self::report("found a match in the session data for store code, setting store code to: $_key");
						break;
					}
				}
			
				self::$storeCode = 'store_default';
				self::report("setting store code to: store_default");
			}
		}
		return self::$storeCode;
	}
	
	public static function getDefaultCurrencyCode() 
	{
		if(!self::$defaultCurrencyCode){
			$sql = "SELECT value FROM ". self::getTableName('core_config_data') ." WHERE path = 'currency/options/default'";
			if(self::useMySqli()){
				//mysqli
				$result = mysqli_query(self::$mysqlidatabase, $sql);
		        if(count($result)){
		            while($row = mysqli_fetch_array($result)){
						self::$defaultCurrencyCode = $row[0];
		            }
				}
			}else{
				//PDO
				if(count($result)){
					foreach(self::$pdodatabase->query($sql) as $row) {
				        while($row = mysqli_fetch_array($result)){
							self::$defaultCurrencyCode = $row[0];
			            }
				    }
				}	
			}
		}
		return self::$defaultCurrencyCode;
	}
	
	public static function useMySqli()
	{
		if (function_exists('mysqli_connect')) {
			//mysqli is installed
			return true;
		}else{
			//Try PDO
			return false;
		}
	}
	
	public static function getDBPrefix()
	{
		//Use alone for higher performance table prefix fetch
		$prefix = '';
		
		//Use for standard table prefix fetch (Lower performance, but no configuration required)
		if(self::$tablePrefix == ''){
			if(self::$config != ''){
				try{
					$config = self::$config;
					$prefix = $config->global->resources->db->table_prefix;
				}catch(Exception $e){}
			}else{
				if(file_exists('app/etc/local.xml')){
					try{
						$config = self::$config = simplexml_load_file('app/etc/local.xml');
						$prefix = $config->global->resources->db->table_prefix;
					}catch(Exception $e){}
				}
			}
			if($prefix != ''){
				$prefix = $prefix."_";
			}
			self::$tablePrefix = $prefix;
			return $prefix;
		}else{
			return self::$tablePrefix;
		}
	}

	public static function getTableName($tableName)
	{
		return self::getDBPrefix() . $tableName;
	}
}
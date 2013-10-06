<?php

/**
 * Server-Side Google Analytics Content Experiments PHP Client
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License (LGPL) as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA.
 * 
 * Google Analytics is a registered trademark of Google Inc.
 * 
 * @link      https://github.com/thomasbachem/php-gacx
 * 
 * @license   http://www.gnu.org/licenses/lgpl.html
 * @author    Thomas Bachem <mail@thomasbachem.com>
 */

namespace UnitedPrototype\GoogleAnalytics;

use Exception;
use UnexpectedValueException;

/**
 * @link https://developers.google.com/analytics/devguides/collection/gajs/experiments
 */
class Experiment {
	
	const GACX_URL = 'http://www.google-analytics.com/cx/api.js';
	
	
	/**
	 * @var string
	 */
	protected $id;
	
	/**
	 * @var array
	 */
	protected $data;
	
	/**
	 * The connection timeout for cURL.
	 * 
	 * @var int
	 */
	protected static $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;
	
	/**
	 * The request timeout for cURL.
	 * 
	 * @var int
	 */
	protected static $timeout = self::DEFAULT_TIMEOUT;
	
	/**
	 * The domain name that is also used by Google Analytics.
	 * Defaults to $_SERVER['HTTP_HOST'] if defined.
	 * 
	 * @var string
	 */
	protected static $domainName = self::DEFAULT_DOMAIN_NAME;
	
	/**
	 * The cookie path that is also used by Google Analytics.
	 * Defaults to "/".
	 * 
	 * @var string
	 */
	protected static $cookiePath = self::DEFAULT_COOKIE_PATH;
	
	/**
	 * The cookie expiration time in seconds.
	 * 
	 * @var int
	 */
	protected static $cookieExpirationSeconds = self::DEFAULT_COOKIE_EXPIRATION_SECONDS;
	
	/**
	 * Optional. A cache directory that will be used to cache
	 * the requests being made to the Google Analytics servers.
	 * 
	 * @var string
	 */
	protected static $cacheDir;
	
	/**
	 * Cache lifetime in seconds. Defines how often new variation
	 * weights will be retrieved from the Google Analytics servers
	 * if caching is used.
	 * 
	 * @var int
	 */
	protected static $cacheTtl = self::DEFAULT_CACHE_TTL;
	
	
	const ORIGINAL_VARIATION  =  0;
	const NO_CHOSEN_VARIATION = -1;
	const NOT_PARTICIPATING   = -2;
	
	const DEFAULT_CONNECT_TIMEOUT           = 2;
	const DEFAULT_TIMEOUT                   = 2;
	const DEFAULT_DOMAIN_NAME               = 'auto';
	const DEFAULT_COOKIE_PATH               = '/';
	const DEFAULT_COOKIE_EXPIRATION_SECONDS = 48211200;
	const DEFAULT_CACHE_TTL                 = 60;
	
	
	/**
	 * @param string $id
	 */
	public function __construct($id) {
		$this->id = $id;
	}
	
	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}
	
	/**
	 * @param int $connectTimeout
	 */
	public static function setConnectTimeout($connectTimeout) {
		static::$connectTimeout = (int)$connectTimeout;
	}
	
	/**
	 * @return int
	 */
	public static function getConnectTimeout() {
		return static::$connectTimeout;
	}
	
	/**
	 * @param int $timeout
	 */
	public static function setTimeout($timeout) {
		static::$timeout = (int)$timeout;
	}
	
	/**
	 * @return int
	 */
	public static function getTimeout() {
		return static::$timeout;
	}
	
	/**
	 * @param string $domainName
	 */
	public static function setDomainName($domainName) {
		static::$domainName = strtolower($domainName);
	}
	
	/**
	 * @return string
	 */
	public static function getDomainName() {
		if(static::$domainName == 'auto') {
			return isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : null;
		} elseif(static::$domainName) {
			return static::$domainName;
		} else {
			throw new UnexpectedValueException('Unable to determine domain name, please provide one via setDomainName()!');
		}
	}
	
	/**
	 * @param string $cookiePath
	 */
	public static function setCookiePath($cookiePath) {
		static::$cookiePath = $cookiePath;
	}
	
	/**
	 * @return string
	 */
	public static function getCookiePath() {
		return static::$cookiePath;
	}
	
	/**
	 * @param int $cookieExpirationSeconds
	 */
	public static function setCookieExpirationSeconds($cookieExpirationSeconds) {
		static::$cookieExpirationSeconds = (int)$cookieExpirationSeconds;
	}
	
	/**
	 * @return int
	 */
	public static function getCookieExpirationSeconds() {
		return static::$cookieExpirationSeconds;
	}
	
	/**
	 * @param string $cacheDir
	 */
	public static function setCacheDir($cacheDir) {
		static::$cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
	}
	
	/**
	 * @return string
	 */
	public static function getCacheDir() {
		return static::$cacheDir;
	}
	
	/**
	 * @param int $cacheTtl
	 */
	public static function setCacheTtl($cacheTtl) {
		self::$cacheTtl = (int)$cacheTtl;
	}
	
	/**
	 * @return int
	 */
	public static function getCacheTtl() {
		return self::$cacheTtl;
	}
	
	/**
	 * @return array
	 */
	protected function getData() {
		if($this->data === null) {
			$this->data = $this->loadData();
		}
		return $this->data;
	}
	
	/**
	 * Retrieves the experiment data from Google Analytics servers. This allows
	 * us to take the different variation weights into account to make use of
	 * the multi-armed bandit algorithm (https://support.google.com/analytics/answer/2844870).
	 * 
	 * @return array
	 */
	protected function loadData() {
		$response = null;
		$cacheDir = static::getCacheDir();
		
		if($cacheDir) {
			$cachePath = $cacheDir . DIRECTORY_SEPARATOR . 'gacx-' . rawurlencode($this->id) . '.cache';
			if(is_readable($cachePath) && time() <= filemtime($cachePath) + static::getCacheTtl()) {
				$response = file_get_contents($cachePath);
			}
		}
		
		if(!$response) {
			if(!extension_loaded('curl')) {
				throw new Exception('php-gacx requires the cURL extension to be installed.');
			}
			
			// We use this JS API trick instead of the official Google Analytics
			// management API as does only work with OAuth
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::getConnectTimeout()); 
			curl_setopt($curl, CURLOPT_TIMEOUT, static::getTimeout());
			curl_setopt($curl, CURLOPT_FAILONERROR, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_VERBOSE, false);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_USERAGENT, '');
			curl_setopt($curl, CURLOPT_URL, self::GACX_URL . '?experiment=' . rawurlencode($this->id));
			$response = curl_exec($curl);
			$error = curl_error($curl);
			curl_close($curl);
			
			if($cacheDir && $response) {
				if(!is_writable($cacheDir)) {
					throw new Exception('Cache directory "' . $cacheDir . '" is not writable.');
				}
				
				file_put_contents($cachePath, $response);
			}
		}
		
		if(!$response) {
			throw new Exception('Unable to retrieve Google Analytics Content Experiments API response: ' . (isset($error) ? $error : '') . (strlen($response) ? "\n" . $response : ''));
		} else {
			// Use a recursive regex to match nested JSON objects
			// (relies on the fact that strings do not contain "{" or "}"!)
			if(!preg_match('#\.experiments_\s*=\s*(\{(?:[^{}]+|(?1))+\})#', $response, $m)) {
				throw new UnexpectedValueException('Unable to find experiments in Google Analytics Content Experiments API response.');
			} else {
				$experiments = json_decode($m[1], true);
				if($experiments === null) {
					throw new UnexpectedValueException('Unable to parse JSON from Google Analytics Content Experiments API response.');
				} else {
					if(isset($experiments[$this->id]['data'])) {
						return $experiments[$this->id]['data'];
					} elseif(isset($experiments[$this->id]['error'])) {
						throw new UnexpectedValueException('Error from Google Analytics Content Experiments API: ' . $experiments[$this->id]['error']['code'] . ' - ' . $experiments[$this->id]['error']['message']);
					} else {
						throw new UnexpectedValueException('Unable to find experiment data in JSON from Google Analytics Content Experiments API response.');
					}
				}
			}
		}
	}
	
	/**
	 * Works just like cxApi.chooseVariation()
	 * 
	 * @link https://developers.google.com/analytics/devguides/collection/gajs/experiments#cxjs-methods
	 * 
	 * @param  string  $utmx        Value of the "__utmx" cookie. Defaults to $_COOKIE['__utmx'] if defined
	 * @param  string  $utmxx       Value of the "__utmxx" cookie. Defaults to $_COOKIE['__utmxx'] if defined
	 * @param  bool    $setCookies  See setChosenVariation() - sets the "__utmx" and "__utmxx" cookies with
	 *                              setrawcookie() if true
	 * @return int
	 */
	public function chooseVariation($utmx = null, $utmxx = null, $setCookies = true) {
		$variation = $this->getChosenVariation($utmx);
		
		if(!$variation) {
			$variation = $this->chooseNewVariation();
			$this->setChosenVariation($variation, $utmx, $utmxx, $setCookies);
		}
		
		return $variation;
	}
	
	/**
	 * @return int
	 */
	public function chooseNewVariation() {
		$data = $this->getData();
		
		// Extracted from Google Analytics Content Experiments JS client
		$rand = mt_rand(0, 1E9) / 1E9;
		foreach($data['items'] as $item) {
			if(isset($item['weight']) && empty($item['disabled'])) {
				// Disabled variations have a weight of 0, so they will never match here
				if($rand < $item['weight']) {
					if($item['id'] !== null) {
						return (int)$item['id'];
					} else {
						return self::NOT_PARTICIPATING;
					}
				}
				$rand -= $item['weight'];
			}
		}
		
		return self::ORIGINAL_VARIATION;
	}
	
	/**
	 * Works just like cxApi.getChosenVariation()
	 * 
	 * @link https://developers.google.com/analytics/devguides/collection/gajs/experiments#cxjs-methods
	 * 
	 * @param  string  $utmx  Value of the "__utmx" cookie. Defaults to $_COOKIE['__utmx'] if defined
	 * @return int|null
	 */
	public function getChosenVariation($utmx = null) {
		if($utmx === null && isset($_COOKIE['__utmx'])) {
			$utmx = $_COOKIE['__utmx'];
		}
		
		// Example format of the "__utmx" cookie value:
		// 159991919.ft-5xaLPSturFXCPgoFrKg$0:1.ft-6uzLPSelrFQsPgouIkD$0:2
		// [DOMAIN_HASH].[EXPERIMENT_ID]$0:[VARIATION].[EXPERIMENT_ID]$0:[VARIATION]
		$experiments = explode('.', $utmx);
		if(count($experiments) > 1) {
			// Strip domain hash
			array_shift($experiments);
			
			foreach($experiments as $experiment) {
				if(preg_match('/^([^$]+)\$([^:]+):(.*)$/', $experiment, $m)) {
					if($m[1] == $this->id) {
						// It seems like there can be stored multiple variations here,
						// but that it is deprecated
						$variations = explode('-', $m[3]);
						return (int)$variations[0];
					}
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Works just like cxApi.setChosenVariation()
	 * 
	 * @link https://developers.google.com/analytics/devguides/collection/gajs/experiments#cxjs-methods
	 *       
	 * @param  int     $variation   Variation number
	 * @param  string  $utmx        Value of the "__utmx" cookie. Defaults to $_COOKIE['__utmx'] if defined
	 * @param  string  $utmxx       Value of the "__utmxx" cookie. Defaults to $_COOKIE['__utmxx'] if defined
	 * @param  bool    $setCookies  Sets the "__utmx" and "__utmxx" cookies with setrawcookie() if true
	 * @return array                Returns an array of cookies to set so you can set the cookies thru your
	 *                              framework's methods if you set $setCookies to false.
	 */
	public function setChosenVariation($variation, $utmx = null, $utmxx = null, $setCookies = true) {
		if($utmx === null && isset($_COOKIE['__utmx'])) {
			$utmx = $_COOKIE['__utmx'];
		}
		if($utmxx === null && isset($_COOKIE['__utmxx'])) {
			$utmxx = $_COOKIE['__utmxx'];
		}
		
		$domainName = static::getDomainName();
		$domainHash = static::generateHash($domainName);
		
		$cookies = array(
			'__utmx'  => array(
				'value'    => $utmx,
				'expire'   => time() + static::getCookieExpirationSeconds(),
				'path'     => static::getCookiePath(),
				'domain'   => '.' . $domainName,
				'secure'   => false,
				'httponly' => false,
			),
			'__utmxx' => array(
				'value'    => $utmxx,
				'expire'   => time() + static::getCookieExpirationSeconds(),
				'path'     => static::getCookiePath(),
				'domain'   => '.' . $domainName,
				'secure'   => false,
				'httponly' => false,
			),
		);
		
		// Example format of the "__utmx" cookie value:
		// 159991919.ft-5xaLPSturFXCPgoFrKg$0:1.ft-6uzLPSelrFQsPgouIkD$0:2
		// [DOMAIN_HASH].[EXPERIMENT_ID]$0:[VARIATION].[EXPERIMENT_ID]$0:[VARIATION]
		$experiments = explode('.', $utmx);
		if(count($experiments) > 1) {
			// Modify "__utmx" cookie value
			$domainHash = array_shift($experiments);
			$found = false;
			foreach($experiments as &$experiment) {
				if(preg_match('/^([^$]+)\$([^:]+):(.*)$/', $experiment, $m)) {
					if($m[1] == $this->id) {
						$found = true;
						$experiment = $m[1] . '$' . $m[2] . ':' . $variation;
					}
				}
			}
			if(!$found) {
				$experiments[] = $this->id . '$0' . ':' . $variation;
			}
			$cookies['__utmx']['value'] = $domainHash . '.' . implode('.', $experiments);
		} else {
			// Create new "__utmx" cookie value
			$cookies['__utmx']['value']  = $domainHash . '.' . $this->id . '$0' . ':' . $variation;
		}
		
		// Example format of the "__utmxx" cookie value:
		// 159991919.ft-5xaLPSturFXCPgoFrKg$0:1380888455:8035200.ft-6uzLPSelrFQsPgouIkD$0:1380888456:8035200
		// [DOMAIN_HASH].[EXPERIMENT_ID]$0:[TIMESTAMP]:8035200.[EXPERIMENT_ID]$0:[TIMESTAMP]:8035200
		$experiments = explode('.', $utmxx);
		if(count($experiments) > 1) {
			// Modify "__utmxx" cookie value
			$domainHash = array_shift($experiments);
			$found = false;
			foreach($experiments as &$experiment) {
				// There is an optional part at the very end of the cookie value
				// which seems not to be used as of now
				if(preg_match('/^([^$]+)\$([^:]+):([^:]+):([^:]+):?(.*)$/', $experiment, $m)) {
					if($m[1] == $this->id) {
						$found = true;
						$experiment = $m[1] . '$' . $m[2] . ':' . time() . ':' . $m[4] . ($m[5] ? ':' . $m[5] : '');
					}
				}
			}
			if(!$found) {
				$experiments[] = $this->id . '$0' . ':' . time() . ':' . 8035200;
			}
			$cookies['__utmxx']['value'] = $domainHash . '.' . implode('.', $experiments);
		} else {
			// Create new "__utmxx" cookie value
			// (the "8035200" value is hardcoded in the GACX JS client)
			$cookies['__utmxx']['value'] = $domainHash . '.' . $this->id . '$0' . ':' . time() . ':' . 8035200;
		}
		
		if($setCookies) {
			foreach($cookies as $name => $cookie) {
				setrawcookie($name, $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
			}
		}
		
		return $cookies;
	}
	
	/**
	 * Generates a valid Google Analytics hash for the input string.
	 * All Google Analytics cookie values begin with a hash of the domain name.
	 * 
	 * @link http://code.google.com/p/gaforflash/source/browse/trunk/src/com/google/analytics/core/Utils.as#44
	 * 
	 * @param  string $string
	 * @return int
	 */
	protected static function generateHash($string) {
		$string = (string)$string;
		$hash = 1;
		
		if($string !== null && $string !== '') {
			$hash = 0;
			
			$length = strlen($string);
			for($pos = $length - 1; $pos >= 0; $pos--) {
				$current   = ord($string[$pos]);
				$hash      = (($hash << 6) & 0xfffffff) + $current + ($current << 14);
				$leftMost7 = $hash & 0xfe00000;
				if($leftMost7 != 0) {
					$hash ^= $leftMost7 >> 21;
				}
			}
		}
		
		return $hash;
	}
	
}

?>
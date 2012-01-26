<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 7331 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/* Debug only */
@ini_set('display_errors', 'on');
define('_PS_DEBUG_SQL_', true);

$start_time = microtime(true);

/* Compatibility warning */
define('_PS_DISPLAY_COMPATIBILITY_WARNING_', true);

/* SSL configuration */
define('_PS_SSL_PORT_', 443);

/* Improve PHP configuration to prevent issues */
ini_set('upload_max_filesize', '100M');
ini_set('default_charset', 'utf-8');
ini_set('magic_quotes_runtime', 0);

// correct Apache charset (except if it's too late
if(!headers_sent())
	header('Content-Type: text/html; charset=utf-8');

/* No settings file? goto installer...*/
if (!file_exists(dirname(__FILE__).'/settings.inc.php'))
{
	$dir = ((is_dir($_SERVER['REQUEST_URI']) OR substr($_SERVER['REQUEST_URI'], -1) == '/') ? $_SERVER['REQUEST_URI'] : dirname($_SERVER['REQUEST_URI']).'/');
	if (!file_exists(dirname(__FILE__).'/../install-dev'))
		die('Error: "install" directory is missing');
	header('Location: install-dev/');
	exit;
}
require_once(dirname(__FILE__).'/settings.inc.php');
require_once(dirname(__FILE__).'/defines.inc.php');
require_once(dirname(__FILE__).'/autoload.php');

if (_PS_DEBUG_PROFILING_)
{
	include_once(_PS_ROOT_DIR_.'/override/classes/_Controller.php');
	include_once(_PS_ROOT_DIR_.'/override/classes/_Module.php');
	include_once(_PS_ROOT_DIR_.'/override/classes/_ObjectModel.php');
	include_once(_PS_ROOT_DIR_.'/override/classes/db/_Db.php');
}

/* Redefine REQUEST_URI if empty (on some webservers...) */
if (!isset($_SERVER['REQUEST_URI']) OR empty($_SERVER['REQUEST_URI']))
{
	if (substr($_SERVER['SCRIPT_NAME'], -9) == 'index.php' && empty($_SERVER['QUERY_STRING']))
		$_SERVER['REQUEST_URI'] = dirname($_SERVER['SCRIPT_NAME']).'/';
	else
	{
		$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
		if (isset($_SERVER['QUERY_STRING']) AND !empty($_SERVER['QUERY_STRING']))
			$_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
	}
}

// Trying to redefine HTTP_HOST if empty (on some webservers...)
if (!isset($_SERVER['HTTP_HOST']) || empty($_SERVER['HTTP_HOST']))
	$_SERVER['HTTP_HOST'] = @getenv('HTTP_HOST');


if (!defined('_PS_MAGIC_QUOTES_GPC_'))
	define('_PS_MAGIC_QUOTES_GPC_',         get_magic_quotes_gpc());

/* Set the current Shop */
Context::getContext()->shop = Shop::initialize();
define('_THEME_NAME_', Context::getContext()->shop->getTheme());
define('__PS_BASE_URI__', Context::getContext()->shop->getBaseURI());

/* Include all defines related to base uri and theme name */
require_once(dirname(__FILE__).'/defines_uri.inc.php');

if (!defined('_PS_MODULE_DIR_'))
	define('_PS_MODULE_DIR_',           _PS_ROOT_DIR_.'/modules/');

global $_MODULES;
$_MODULES = array();

/* Load all configuration keys */
Configuration::loadConfiguration();

/* Load all language definitions */
Language::loadLanguages();

/* Loading default country */
$defaultCountry = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));
Context::getContext()->country = $defaultCountry;

/* It is not safe to rely on the system's timezone settings, and this would generate a PHP Strict Standards notice. */
@date_default_timezone_set(Configuration::get('PS_TIMEZONE'));

/* Instantiate cookie */
$cookieLifetime = (time() + (((int)Configuration::get('PS_COOKIE_LIFETIME_BO') > 0 ? (int)Configuration::get('PS_COOKIE_LIFETIME_BO') : 1)* 3600));
if (defined('_PS_ADMIN_DIR_'))
	$cookie = new Cookie('psAdmin', '', $cookieLifetime);
else
	$cookie = new Cookie('ps'.Context::getContext()->shop->getID(), '', $cookieLifetime);
Context::getContext()->cookie = $cookie;

/* Create employee if in BO, customer else */
if (defined('_PS_ADMIN_DIR_'))
{
	$employee = new Employee($cookie->id_employee);
	Context::getContext()->employee = $employee;

	$cookie->id_lang = (int)$employee->id_lang;
}
else
{
	if (isset($cookie->id_customer) && (int)$cookie->id_customer)
	{
		$customer = new Customer($cookie->id_customer);
		$customer->logged = $cookie->logged;

		if (!isset($cookie->id_cart))
		{
			$shops_share = Context::getContext()->shop->getListOfID(Shop::SHARE_ORDER);
			$id_cart = Db::getInstance()->getValue('SELECT `id_cart` FROM `'._DB_PREFIX_.'cart` WHERE `id_customer` = "'.(int)$customer->id.'" AND `id_shop` IN ("'.implode('","', $shops_share).'") ORDER BY `id_cart` DESC');
			if ($id_cart != false)
				$cookie->id_cart = $id_cart;
		}
	}
	else
	{
		$customer = new Customer();
		
		// Change the default group 
		if (Group::isFeatureActive())
			$customer->id_default_group = Configuration::get('PS_UNIDENTIFIED_GROUP');
	}

	$customer->id_guest = $cookie->id_guest;
	Context::getContext()->customer = $customer;
}

// if the language stored in the cookie is not available language, use default language
if (isset($cookie->id_lang) && $cookie->id_lang)
	$language = new Language($cookie->id_lang);
if (!isset($language) || !Validate::isLoadedObject($language))
	$language = new Language(Configuration::get('PS_LANG_DEFAULT'));
Context::getContext()->language = $language;

/* Define order state */
// DEPRECATED : these defines are going to be deleted on 1.6 version of Prestashop
// USE : Configuration::get() method in order to getting the id of order state
define('_PS_OS_CHEQUE_',      Configuration::get('PS_OS_CHEQUE'));
define('_PS_OS_PAYMENT_',     Configuration::get('PS_OS_PAYMENT'));
define('_PS_OS_PREPARATION_', Configuration::get('PS_OS_PREPARATION'));
define('_PS_OS_SHIPPING_',    Configuration::get('PS_OS_SHIPPING'));
define('_PS_OS_DELIVERED_',   Configuration::get('PS_OS_DELIVERED'));
define('_PS_OS_CANCELED_',    Configuration::get('PS_OS_CANCELED'));
define('_PS_OS_REFUND_',      Configuration::get('PS_OS_REFUND'));
define('_PS_OS_ERROR_',       Configuration::get('PS_OS_ERROR'));
define('_PS_OS_OUTOFSTOCK_',  Configuration::get('PS_OS_OUTOFSTOCK'));
define('_PS_OS_BANKWIRE_',    Configuration::get('PS_OS_BANKWIRE'));
define('_PS_OS_PAYPAL_',      Configuration::get('PS_OS_PAYPAL'));
define('_PS_OS_WS_PAYMENT_', Configuration::get('PS_OS_WS_PAYMENT'));

/* Smarty */
require_once(dirname(__FILE__).'/smarty.config.inc.php');

Context::getContext()->smarty = $smarty;
/* Possible value are true, false, 'URL'
 (for 'URL' append SMARTY_DEBUG as a parameter to the url)
 default is false for production environment */
define('SMARTY_DEBUG_CONSOLE', false);

<?php
/*
* 2007-2012 PrestaShop
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
*  @copyright  2007-2012 PrestaShop SA
*  @version  Release: $Revision$
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class ShopCore extends ObjectModel
{
	public	$id_group_shop;
	public	$id_theme;
	public	$name;
	public	$active = true;
	public	$id_category;
	public	$deleted;

	public $theme_name;
	public $theme_directory;
	public $physical_uri;
	public $virtual_uri;
	public $domain;
	public $domain_ssl;

	/**
	 * @var GroupShop
	 */
	protected $group;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'shop',
		'primary' => 'id_shop',
		'fields' => array(
			'active' => 		array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
			'deleted' => 		array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
			'name' => 			array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64),
			'id_theme' => 		array('type' => self::TYPE_INT, 'required' => true),
			'id_category' => 	array('type' => self::TYPE_INT, 'required' => true),
			'id_group_shop' => 	array('type' => self::TYPE_INT, 'required' => true),
		),
	);

	/** @var array List of shops cached */
	protected static $shops;

	private	static $asso_tables = array(
		'carrier' => array('type' => 'shop'),
		'carrier_lang' => array('type' => 'fk_shop'),
		'category' => array('type' => 'shop'),
		'category_lang' => array('type' => 'fk_shop'),
		'cms' => array('type' => 'shop'),
		'contact' => array('type' => 'shop'),
		'country' => array('type' => 'shop'),
		'currency' => array('type' => 'shop'),
		'employee' => array('type' => 'shop'),
		'hook_module' => array('type' => 'fk_shop'),
		'hook_module_exceptions' =>	array('type' => 'fk_shop', 'primary' => 'id_hook_module_exceptions'),
		'image' => array('type' => 'shop'),
		'lang' => array('type' => 'shop'),
		'meta_lang' => array('type' => 'fk_shop'),
		'module' => array('type' => 'shop'),
		'module_currency' => array('type' => 'fk_shop'),
		'module_country' => array('type' => 'fk_shop'),
		'module_group' => array('type' => 'fk_shop'),
		'product' => array('type' => 'shop'),
		'product_lang' => array('type' => 'fk_shop'),
		'referrer' => array('type' => 'shop'),
		'scene' => array('type' => 'shop'),
		'store' => array('type' => 'shop'),
		'webservice_account' => array('type' => 'shop'),
		'warehouse' => array('type' => 'shop'),
		'stock_available' => array('type' => 'fk_shop'),
		'product_tax_rules_group_shop' => array('type' => 'fk_shop'),
		'carrier_tax_rules_group_shop' => array('type' => 'fk_shop'),
	);

	protected $webserviceParameters = array(
		'fields' => array(
			'id_group_shop' => array('xlink_resource' => 'shop_groups'),
			'id_category' => array(),
			'id_theme' => array(),
		),
	);

    protected static $context;
    protected static $context_id_shop;
    protected static $context_id_group_shop;

	/**
	 * There are 3 kinds of shop context : shop, group shop and general
	 */
	const CONTEXT_SHOP = 1;
	const CONTEXT_GROUP = 2;
	const CONTEXT_ALL = 4;

	/**
	 * Some data can be shared between shops, like customers or orders
	 */
	const SHARE_CUSTOMER = 'share_customer';
	const SHARE_ORDER = 'share_order';

	public function __construct($id = null, $id_lang = null, $id_shop = null)
	{
		parent::__construct($id, $id_lang, $id_shop);
		if ($this->id)
		{
			$sql = 'SELECT su.physical_uri, su.virtual_uri,
				su.domain, su.domain_ssl, t.id_theme, t.name, t.directory
					FROM '._DB_PREFIX_.'shop s
					LEFT JOIN '._DB_PREFIX_.'shop_url su ON (s.id_shop = su.id_shop)
					LEFT JOIN '._DB_PREFIX_.'theme t ON (t.id_theme = s.id_theme)
					WHERE s.id_shop = '.(int)$this->id.'
						AND s.active = 1
						AND s.deleted = 0
						AND su.main = 1';
			if (!$row = Db::getInstance()->getRow($sql))
				return;

			$this->theme_id = $row['id_theme'];
			$this->theme_name = $row['name'];
			$this->theme_directory = $row['directory'];
			$this->physical_uri = $row['physical_uri'];
			$this->virtual_uri = $row['virtual_uri'];
			$this->domain = $row['domain'];
			$this->domain_ssl = $row['domain_ssl'];
		}
	}

	public function add($autodate = true, $null_values = false)
	{
		$res = parent::add($autodate, $null_values);
		Shop::cacheShops(true);
		return $res;
	}

	public function delete()
	{
		if (Shop::hasDependency($this->id) || !$res = parent::delete())
			return false;

		foreach (Shop::getAssoTables() as $table_name => $row)
		{
			$id = 'id_'.$row['type'];
			if ($row['type'] == 'fk_shop')
				$id = 'id_shop';
			else
				$table_name .= '_'.$row['type'];
			$res &= Db::getInstance()->execute('
				DELETE FROM `'.bqSQL(_DB_PREFIX_.$table_name).'`
				WHERE `'.bqSQL($id).'`='.(int)$this->id
			);
		}

		// removes stock available
		$res &= Db::getInstance()->delete('stock_available', 'id_shop = '.(int)$this->id);

		// Remove urls
		$res &= Db::getInstance()->delete('shop_url', 'id_shop = '.(int)$this->id);

		Shop::cacheShops(true);

		return $res;
	}

	/**
	 * Detect dependency with customer or orders
	 *
	 * @return bool
	 */
	public static function hasDependency($id_shop)
	{
		$has_dependency = false;
		$nbr_customer = (int)Db::getInstance()->getValue('
			SELECT count(*)
			FROM `'._DB_PREFIX_.'customer`
			WHERE `id_shop`='.(int)$id_shop
		);
		if ($nbr_customer)
			$has_dependency = true;
		else
		{
			$nbr_order = (int)Db::getInstance()->getValue('
				SELECT count(*)
				FROM `'._DB_PREFIX_.'orders`
				WHERE `id_shop`='.(int)$id_shop
			);
			if ($nbr_order)
				$has_dependency = true;
		}

		return $has_dependency;
	}

	/**
	 * Get a new instance of a shop
	 *
	 * @param int $id shop ID
	 * @return Shop
	 */
	public static function getInstance($id)
	{
		return new Shop($id);
	}

	/**
	 * Find the shop from current domain / uri and get an instance of this shop
	 * if INSTALL_VERSION is defined, will return an empty shop object
	 *
	 * @return Shop
	 */
	public static function initialize()
	{
		// Get list of excluded uri
		$dirname = dirname(__FILE__);
		$directories = scandir($dirname.'/../');
		$excluded_uris = array();
		foreach ($directories as $directory)
			if (is_dir($dirname.'/../'.$directory) && (!preg_match ('/^\./', $directory)))
				$excluded_uris[] = $directory;

		// Find current shop from URL
		if (!($id_shop = Tools::getValue('id_shop')) && !defined('_PS_ADMIN_DIR_'))
		{
			$sql = 'SELECT s.id_shop, CONCAT(su.physical_uri, su.virtual_uri) AS uri, su.domain, su.main
					FROM '._DB_PREFIX_.'shop_url su
					LEFT JOIN '._DB_PREFIX_.'shop s ON (s.id_shop = su.id_shop)
					WHERE su.domain = \''.pSQL(Tools::getHttpHost()).'\'
						AND s.active = 1
						AND s.deleted = 0
					ORDER BY LENGTH(uri) DESC';

			$id_shop = '';
			$found_uri = '';
			$request_uri = rawurldecode($_SERVER['REQUEST_URI']);
			if ($results = Db::getInstance()->executeS($sql))
				foreach ($results as $row)
				{
					// An URL matching current shop was found
					if (!$id_shop && preg_match('#^'.preg_quote($row['uri'], '#').'#', $request_uri))
					{
						$id_shop = $row['id_shop'];
						$found_uri = $row['uri'];

						// If this is the main URL, use it in current script
						if ($row['main'])
							break;
					}
					else if ($id_shop && $row['main'])
					{
						// If an URL was found but is not current URL, redirect to main URL
						$request_uri = substr($request_uri, strlen($found_uri));
						header('HTTP/1.1 301 Moved Permanently');
						header('Cache-Control: no-cache');
						header('location: http://'.$row['domain'].$row['uri'].$request_uri);
						exit;
					}
				}
		}

		if (!$id_shop && defined('_PS_ADMIN_DIR_'))
		{
			// If in admin, we can access to the shop without right URL
			$shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
			$shop->physical_uri = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/';
			$shop->virtual_uri = '';
			return $shop;
		}

		$shop = new Shop($id_shop);
		if (!Validate::isLoadedObject($shop) || !$shop->active || !$id_shop)
		{
			// No shop found ... too bad, let's redirect to default shop
			$default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));

			// Hmm there is something really bad in your Prestashop !
			if (!Validate::isLoadedObject($default_shop))
				throw new PrestaShopException('Shop not found');

			$url = 'http://'.$default_shop->domain.$default_shop->getBaseURI().'index.php?'.$_SERVER['QUERY_STRING'];
			header('location: '.$url);
			exit;
		}

		return $shop;
	}

	/**
	* @return Address the current shop address
	*/
	public function getAddress()
	{
		if (!isset($this->address))
		{
			$address = new Address();
			$address->company = Configuration::get('PS_SHOP_NAME');
			$address->id_country = Configuration::get('PS_SHOP_COUNTRY_ID');
			$address->id_state = Configuration::get('PS_SHOP_STATE_ID');
			$address->address1 = Configuration::get('PS_SHOP_ADDR1');
			$address->address2 = Configuration::get('PS_SHOP_ADDR2');
			$address->postcode = Configuration::get('PS_SHOP_CODE');
			$address->city = Configuration::get('PS_SHOP_CITY');

			$this->address = $address;
		}

		return $this->address;
	}

	/**
	 * Get shop theme name
	 *
	 * @return string
	 */
	public function getTheme()
	{
		return $this->theme_directory;
	}

	/**
	 * Get shop URI
	 *
	 * @return string
	 */
	public function getBaseURI()
	{
		return $this->physical_uri.$this->virtual_uri;
	}

	public function getPhysicalURI()
	{
		return $this->physical_uri;
	}

	/**
	 * Get shop URL
	 *
	 * @return string
	 */
	public function getBaseURL()
	{
		if (!$this->domain)
			return false;
		return 'http://'.$this->domain.$this->getBaseURI();
	}

	/**
	 * Get group of current shop
	 *
	 * @return GroupShop
	 */
	public function getGroup()
	{
		if (!$this->group)
			$this->group = new GroupShop($this->id_group_shop);
		return $this->group;
	}

	/**
	 * Get root category of current shop
	 *
	 * @return int
	 */
	public function getCategory()
	{
		return ($this->id_category) ? $this->id_category : 1;
	}

	/**
	 * Get list of shop's urls
	 *
	 * @return array
	 */
	public function getUrls()
	{
		$sql = 'SELECT *
				FROM '._DB_PREFIX.'shop_url
				WHERE active = 1
					AND id_shop = '.(int)$this->id;
		return Db::getInstance()->executeS($sql);
	}

	/**
	 * Check if current shop ID is the same as default shop in configuration
	 *
	 * @return bool
	 */
	public function isDefaultShop()
	{
		return ($this->id == Configuration::get('PS_SHOP_DEFAULT'));
	}

	/**
	 * Get list of associated tables to shop
	 *
	 * @return array
	 */
	public static function getAssoTables()
	{
		return self::$asso_tables;
	}

	/**
	 * Load list of groups and shops, and cache it
	 *
	 * @param bool $refresh
	 */
	public static function cacheShops($refresh = false)
	{
		if (!is_null(self::$shops) && !$refresh)
			return;

		self::$shops = array();

		$select = '';
		$from = '';
		$where = '';

		$employee = Context::getContext()->employee;

		// If the profile isn't a superAdmin
		if (Validate::isLoadedObject($employee) && $employee->id_profile != _PS_ADMIN_PROFILE_)
		{
			$select .= ', es.id_employee';
			$from .= 'LEFT JOIN '._DB_PREFIX_.'employee_shop es ON es.id_shop = s.id_shop';
			$where .= 'AND es.id_employee = '.(int)$employee->id;
		}

		$sql = 'SELECT gs.*, s.*, gs.name AS group_name, s.name AS shop_name, s.active, su.domain, su.domain_ssl, su.physical_uri, su.virtual_uri'.$select.'
				FROM '._DB_PREFIX_.'group_shop gs
				LEFT JOIN '._DB_PREFIX_.'shop s
					ON s.id_group_shop = gs.id_group_shop
				LEFT JOIN '._DB_PREFIX_.'shop_url su
					ON s.id_shop = su.id_shop AND su.main = 1
				'.$from.'
				WHERE s.deleted = 0
					AND gs.deleted = 0
					'.$where.'
				ORDER BY gs.name, s.name';

		if ($results = Db::getInstance()->executeS($sql))
		{
			foreach ($results as $row)
			{
				if (!isset(self::$shops[$row['id_group_shop']]))
					self::$shops[$row['id_group_shop']] = array(
						'id' =>				$row['id_group_shop'],
						'name' => 			$row['group_name'],
						'share_customer' =>	$row['share_customer'],
						'share_order' =>	$row['share_order'],
						'totalShops' =>		Shop::getTotalShopsByIdGroupShop($row['id_group_shop']),
						'shops' => 			array(),
					);

				self::$shops[$row['id_group_shop']]['shops'][$row['id_shop']] = array(
					'id_shop' =>		$row['id_shop'],
					'id_group_shop' =>	$row['id_group_shop'],
					'name' =>			$row['shop_name'],
					'id_theme' =>		$row['id_theme'],
					'id_category' =>	$row['id_category'],
					'domain' =>			$row['domain'],
					'domain_ssl' =>		$row['domain_ssl'],
					'uri' =>			$row['physical_uri'].$row['virtual_uri'],
					'active' =>			$row['active'],
				);
			}
		}
	}

	/**
	 * Get shops list
	 *
	 * @param bool $active
	 * @param int $id_group_shop
	 * @param bool $get_as_list_id
	 */
	public static function getShops($active = true, $id_group_shop = null, $get_as_list_id = false)
	{
		Shop::cacheShops();

		$results = array();
		foreach (self::$shops as $group_id => $group_data)
			foreach ($group_data['shops'] as $id => $shop_data)
				if ((!$active || $shop_data['active']) && (!$id_group_shop || $id_group_shop == $group_id))
				{
					if ($get_as_list_id)
						$results[$id] = $id;
					else
						$results[$id] = $shop_data;
				}
		return $results;
	}

	/**
	 * Return some informations cached for one shop
	 *
	 * @param int $shop_id
	 * @return array
	 */
	public static function getShop($shop_id)
	{
		Shop::cacheShops();
		foreach (self::$shops as $group_id => $group_data)
			if (array_key_exists($shop_id, $group_data['shops']))
				return $group_data['shops'][$shop_id];
		return false;
	}

	/**
	 * Return a shop ID from shop name
	 *
	 * @param string $name
	 * @return int
	 */
	public static function getIdByName($name)
	{
		Shop::cacheShops();
		foreach (self::$shops as $group_data)
			foreach ($group_data['shops'] as $shop_id => $shop_data)
				if (Tools::strtolower($shop_data['name']) == Tools::strtolower($name))
					return $shop_id;
		return false;
	}

	/**
	 * @return int Total of shops
	 */
	public static function getTotalShops($active = true)
	{
		return count(Shop::getShops($active));
	}

	/**
	 * @return int Total of shops
	 */
	public static function getTotalShopsWhoExists()
	{
		return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'shop`');
	}

	/**
	 * @return int Total of shops
	 */
	public static function getTotalShopsByIdGroupShop($id)
	{
		return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'shop` WHERE `id_group_shop` = '.(int)$id);
	}

	public static function getIdShopsByIdGroupShop($id)
	{
		$result = Db::getInstance()->executeS('SELECT `id_shop`, `id_group_shop` FROM `'._DB_PREFIX_.'shop` WHERE `id_group_shop` = '.(int)$id);
		$data = array();
		foreach ($result as $group_data)
			$data[] = (int)$group_data['id_shop'];
		return $data;
	}

	/**
	 * Retrieve group ID of a shop
	 *
	 * @param int $shop_id Shop ID
	 * @return int Group ID
	 */
	public static function getGroupFromShop($shop_id, $as_id = true)
	{
		Shop::cacheShops();
		foreach (self::$shops as $group_id => $group_data)
			if (array_key_exists($shop_id, $group_data['shops']))
				return ($as_id) ? $group_id : $group_data;
		return false;
	}

	/**
	 * If the shop group has the option $type activated, get all shops ID of this group, else get current shop ID
	 *
	 * @param int $shop_id
	 * @param int $type Shop::SHARE_CUSTOMER | Shop::SHARE_ORDER
	 * @return array
	 */
	public static function getSharedShops($shop_id, $type)
	{
		if (!in_array($type, array(Shop::SHARE_CUSTOMER, Shop::SHARE_ORDER)))
			die('Wrong argument ($type) in Shop::getSharedShops() method');

		Shop::cacheShops();
		foreach (self::$shops as $group_data)
			if (array_key_exists($shop_id, $group_data['shops']) && $group_data[$type])
				return array_keys($group_data['shops']);
		return array($shop_id);
	}

	/**
	 * Get a list of ID concerned by the shop context (E.g. if context is shop group, get list of children shop ID)
	 *
	 * @param string $share If false, dont check share datas from group. Else can take a Shop::SHARE_* constant value
	 * @return array
	 */
	public static function getContextListShopID($share = false)
	{
		if (Shop::getContext() == Shop::CONTEXT_SHOP)
			$list = ($share) ? Shop::getSharedShops(Shop::getContextShopID(), $share) : array(Shop::getContextShopID());
		else if (Shop::getContext() == Shop::CONTEXT_GROUP)
			$list = Shop::getShops(true, Shop::getContextGroupShopID(), true);
		else
			$list = Shop::getShops(true, null, true);

		return $list;
	}

	/**
	 * Return the list of shop by id
	 *
	 * @param int $id
	 * @param string $identifier
	 * @param string $table
	 * @return array
	 */
	public static function getShopById($id, $identifier, $table)
	{
		return Db::getInstance()->executeS('SELECT `id_shop`, `'.pSQL($identifier).'` FROM `'._DB_PREFIX_.pSQL($table).'_shop` WHERE `'.pSQL($identifier).'` = '.(int)$id);
	}

	public static function setContext($type, $id = null)
	{
		switch ($type)
		{
			case self::CONTEXT_ALL :
				self::$context_id_shop = null;
				self::$context_id_group_shop = null;
			break;

			case self::CONTEXT_GROUP :
				self::$context_id_shop = null;
				self::$context_id_group_shop = (int)$id;
			break;

			case self::CONTEXT_SHOP :
				self::$context_id_shop = (int)$id;
				self::$context_id_group_shop = Shop::getGroupFromShop($id);
			break;

			default :
				throw new PrestaShopException('Unknown context for shop');
		}

		self::$context = $type;
	}

	public static function getContext()
	{
		return self::$context;
	}

	public static function getContextShopID()
	{
		return self::$context_id_shop;
	}

	public static function getContextGroupShopID()
	{
		return self::$context_id_group_shop;
	}

	/**
	 * Add an sql restriction for shops fields
	 *
	 * @param int $share If false, dont check share datas from group. Else can take a Shop::SHARE_* constant value
	 * @param string $alias
	 * @param string $type shop|group_shop
	 */
	public static function addSqlRestriction($share = false, $alias = null, $type = 'shop')
	{
		if ($type != 'shop' && $type != 'group_shop')
			$type = 'shop';

		if ($alias)
			$alias .= '.';

		$restriction = '';
		if ($type == 'group_shop')
		{
			if (Shop::getContext() != Shop::CONTEXT_ALL)
				$restriction = ' AND '.$alias.'id_group_shop = '.Shop::getContextGroupShopID().' ';
		}
		else
		{
			if (Shop::getContext() != Shop::CONTEXT_ALL)
				$restriction = ' AND '.$alias.'id_shop IN ('.implode(', ', Shop::getContextListShopID($share)).') ';
			//else if ($share == Shop::SHARE_STOCK)
			//	$restriction = ' AND '.$alias.'id_shop = '.$this->getID(true);
		}

		return $restriction;
	}

	/**
	 * Add an SQL JOIN in query between a table and its associated table in multishop
	 *
	 * @param string $table Table name (E.g. product, module, etc.)
	 * @param string $alias Alias of table
	 * @param bool $inner_join Use or not INNER JOIN
	 * @param Context $context
	 * @return string
	 */
	public static function addSqlAssociation($table, $alias, $inner_join = true)
	{
		$table_alias = ' asso_shop_'.$table;
		if (strpos($table, '.') !== false)
			list($table_alias, $table) = explode('.', $table);

		$asso_tables = Shop::getAssoTables();
		if (!isset($asso_tables[$table]) || $asso_tables[$table]['type'] != 'shop')
			return;

		$sql = (($inner_join) ? ' INNER' : ' LEFT').' JOIN '._DB_PREFIX_.$table.'_shop '.$table_alias.'
					ON '.$table_alias.'.id_'.$table.' = '.$alias.'.id_'.$table.'
					AND '.$table_alias.'.id_shop IN('.implode(', ', Shop::getContextListShopID()).') ';
		return $sql;
	}

	/**
	 * Add a restriction on id_shop for multishop lang table
	 *
	 * @param string $alias
	 * @param Context $context
	 * @return string
	 */
	public static function addSqlRestrictionOnLang($alias = null, $id_shop = null)
	{
		if (is_null($id_shop))
			$id_shop = Context::getContext()->shop->id;
		return ' AND '.(($alias) ? $alias.'.' : '').'id_shop = '.$id_shop.' ';
	}

	/**
	 * Get all groups and associated shops as subarrays
	 *
	 * @return array
	 */
	public static function getTree()
	{
		Shop::cacheShops();
		return self::$shops;
	}

	/**
	 * @return bool Return true if there is more than one shop
	 */
	public static function isFeatureActive()
	{
		static $total = null;

		if (is_null($total))
			$total = Shop::getTotalShops(true);
		return ($total > 1) ? true : false;
	}

	public function copyShopData($old_id, $tables_import = false, $deleted = false)
	{
		if (isset($tables_import['product']))
			$tables_import['product_tax_rules_group_shop'] = true;
			
		if (isset($tables_import['carrier']))
			$tables_import['carrier_tax_rules_group_shop'] = true;

		foreach (Shop::getAssoTables() as $table_name => $row)
		{
			if ($tables_import && !isset($tables_import[$table_name]))
				continue;

			// Special case for stock_available if current shop is in a share stock group
			if ($table_name == 'stock_available')
			{
				$group = new GroupShop($this->id_group_shop);
				if ($group->share_stock && $group->haveShops())
					continue;
			}

			$id = 'id_'.$row['type'];
			if ($row['type'] == 'fk_shop')
				$id = 'id_shop';
			else
				$table_name .= '_'.$row['type'];

			if (!$deleted)
			{
				$res = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.$table_name.'` WHERE `'.$id.'` = '.(int)$old_id);
				if ($res)
				{
					unset($res[$id]);
					if (isset($row['primary']))
						unset($res[$row['primary']]);

					$keys = implode(', ', array_keys($res));
					$sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.$table_name.'` ('.$keys.', '.$id.')
								(SELECT '.$keys.', '.(int)$this->id.' FROM '._DB_PREFIX_.$table_name.'
								WHERE `'.$id.'` = '.(int)$old_id.')';
					Db::getInstance()->execute($sql);
				}
			}
			/*else
			{
				Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.$table_name.'` SET  WHERE `'.$id.'`='.(int)$old_id);
			}*/
		}
	}

	public function checkIfShopExist($id)
	{
		return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM`'._DB_PREFIX_.'shop` WHERE `id_shop` = '.(int)$id);
	}

	public function checkIfGroupShopExist($id)
	{
		return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM`'._DB_PREFIX_.'group_shop` WHERE `id_group_shop` = '.(int)$id);
	}

	/**
	 * @deprecated 1.5.0 Use shop->id
	 */
	public static function getCurrentShop()
	{
		Tools::displayAsDeprecated();
		return Context::getContext()->shop->id;
	}

	/**
	 * @static
	 * @param int $id
	 * @return array
	 */
	public static function getCategories($id = 0, $only_id = true)
	{
		// build query
		$query = new DbQuery();
		if ($only_id)
			$query->select('cs.`id_category`');
		else
			$query->select('DISTINCT cs.`id_category`, cl.`name`, cl.`link_rewrite`');
		$query->from('category_shop', 'cs');
		$query->leftJoin('category_lang', 'cl', 'cl.`id_category` = cs.`id_category` AND cl.`id_lang` = '.(int)Context::getContext()->language->id);
		$query->where('cs.`id_shop` = '.(int)$id);
		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

		if ($only_id)
		{
			$array = array();
			foreach ($result as $row)
				$array[] = $row['id_category'];
			$array = array_unique($array);
		}
		else
			return $result;

		return $array;
	}

	/**
	 * @static
	 * @param $id_category
	 * @return bool
	 */
	public static function isCategoryAvailable($id_category)
	{
		return (bool)Db::getInstance()->getValue('
		SELECT `id_category`
		FROM `'._DB_PREFIX_.'category_shop`
		WHERE `id_category` = '.(int)$id_category.'
		AND `id_shop` = '.(int)Context::getContext()->shop->id);
	}

	/**
	 * @static
	 * @param $id_product
	 * @return bool
	 */
	public static function isProductAvailable($id_product)
	{
		$id = Context::getContext()->shop->id;
		$id_shop = $id ? $id : Configuration::get('PS_SHOP_DEFAULT');
		return (bool)Db::getInstance()->getValue('
		SELECT p.`id_product`
		FROM `'._DB_PREFIX_.'product` p
		LEFT JOIN `'._DB_PREFIX_.'category_product` cp
			ON p.`id_product` = cp.`id_product`
		LEFT JOIN `'._DB_PREFIX_.'category_shop` cs
			ON (cp.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int)$id_shop.')
		WHERE p.`id_product` = '.(int)$id_product.'
		AND cs.`id_shop` = '.(int)Context::getContext()->shop->id);
	}
}

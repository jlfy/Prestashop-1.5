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
*  @version  Release: $Revision: 7480 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class NewProductsControllerCore extends FrontController
{
	public $php_self = 'new-products';

	public function setMedia()
	{
		parent::setMedia();
		$this->addCSS(_THEME_CSS_DIR_.'product_list.css');
	}

	/**
	 * Assign template vars related to page content
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		$this->productSort();

		$nbProducts = (int)Product::getNewProducts($this->context->language->id, (isset($this->p) ? (int)($this->p) - 1 : null), (isset($this->n) ? (int)($this->n) : null), true);
		$this->pagination($nbProducts);

		$this->context->smarty->assign(array(
			'products' => Product::getNewProducts($this->context->language->id, (int)($this->p) - 1, (int)($this->n), false, $this->orderBy, $this->orderWay),
			'add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'),
			'nbProducts' => (int)($nbProducts),
			'homeSize' => Image::getSize('home')
		));

		$this->setTemplate(_PS_THEME_DIR_.'new-products.tpl');
	}
}


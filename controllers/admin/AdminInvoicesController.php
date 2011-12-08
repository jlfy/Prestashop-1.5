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
*  @version  Release: $Revision: 7310 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AdminInvoicesControllerCore extends AdminController
{
	public function __construct()
	{
		$this->table = 'invoice';

		$this->options = array(
			'general' => array(
				'title' =>	$this->l('Invoice options'),
				'fields' =>	array(
					'PS_INVOICE' => array(
						'title' => $this->l('Enable invoices:'),
						'desc' => $this->l('Select whether or not to activate invoices for your shop'),
						'cast' => 'intval',
						'type' => 'bool'
					),
					'PS_INVOICE_PREFIX' => array(
						'title' => $this->l('Invoice prefix:'),
						'desc' => $this->l('Prefix used for invoices'),
						'size' => 6,
						'type' => 'textLang'
					),
					'PS_INVOICE_START_NUMBER' => array(
						'title' => $this->l('Invoice number:'),
						'desc' => $this->l('The next invoice will begin with this number, and then increase with each additional invoice. 
							Set to 0 if you want to keep the current number (#').(Order::getLastInvoiceNumber() + 1).').',
						'size' => 6,
						'type' => 'text',
						'cast' => 'intval'
					),
					'PS_INVOICE_FREE_TEXT' => array(
						'title' => $this->l('Free Text:'),
						'desc' => $this->l('This text will appear at the bottom of the invoice'),
						'size' => 6,
						'type' => 'textareaLang',
						'cols' => 40,
						'rows' => 8
					)
				),
				'submit' => array()
			),
		);

		parent::__construct();
	}

	public function initFormByDate()
	{
		$this->fields_form = array(
			'legend' => array(
				'title' => $this->l('By date'),
				'image' => '../img/admin/pdf.gif'
			),
			'input' => array(
				array(
					'type' => 'date',
					'label' => $this->l('From:'),
					'name' => 'date_from',
					'size' => 20,
					'maxlength' => 10,
					'required' => true,
					'desc' => $this->l('Format: 2007-12-31 (inclusive)')
				),
				array(
					'type' => 'date',
					'label' => $this->l('To:'),
					'name' => 'date_to',
					'size' => 20,
					'maxlength' => 10,
					'required' => true,
					'desc' => $this->l('Format: 2008-12-31 (inclusive)')
				)
			),
			'submit' => array(
				'title' => $this->l('Generate PDF file by date'),
				'class' => 'button',
				'id' => 'submitPrint'
			)
		);

		$this->fields_value = array(
			'date_from' => date('Y-m-d'),
			'date_to' => date('Y-m-d')
		);

		$this->table = 'invoice_date';
		$this->toolbar_title = $this->l('Print PDF invoices');
		return parent::renderForm();
	}

	public function initFormByStatus()
	{
		$this->fields_form = array(
			'legend' => array(
				'title' => $this->l('By statuses'),
				'image' => '../img/admin/pdf.gif'
			),
			'input' => array(
				array(
					'type' => 'checkboxStatuses',
					'label' => $this->l('Statuses:'),
					'name' => 'id_order_state',
					'values' => array(
						'query' => OrderState::getOrderStates($this->context->language->id),
						'id' => 'id_order_state',
						'name' => 'name'
					),
					'desc' => $this->l('You can also export orders which have not been charged yet').' (<img src="../img/admin/charged_ko.gif" alt="" />).'
				)
			),
			'submit' => array(
				'title' => $this->l('Generate PDF file by status'),
				'class' => 'button',
				'id' => 'submitPrint2'
			)
		);

		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT COUNT(*) as nbOrders, (
				SELECT oh.id_order_state
				FROM '._DB_PREFIX_.'order_history oh
				WHERE oh.id_order = oi.id_order
				ORDER BY oh.date_add DESC, oh.id_order_history DESC
				LIMIT 1
			) id_order_state
			FROM '._DB_PREFIX_.'order_invoice oi
			LEFT JOIN '._DB_PREFIX_.'orders o ON (oi.id_order = o.id_order)
			WHERE o.id_shop IN('.implode(', ', $this->context->shop->getListOfID()).')
			GROUP BY id_order_state
		');

		$status_stats = array();
		foreach ($result as $row)
			$status_stats[$row['id_order_state']] = $row['nbOrders'];

		$this->tpl_form_vars = array(
			'statusStats' => $status_stats,
			'style' => ''
		);

		$this->table = 'invoice_status';
		$this->show_toolbar = false;
		return parent::renderForm();
	}

	public function initContent()
	{
		$this->display = 'edit';
		$this->initToolbar();
		$this->content .= $this->initFormByDate();
		$this->content .= $this->initFormByStatus();
		$this->table = 'invoice';
		$this->content .= $this->renderOptions();

		$this->context->smarty->assign(array(
			'content' => $this->content,
			'url_post' => self::$currentIndex.'&token='.$this->token,
		));
	}

	public function initToolbar()
	{
		$this->toolbar_btn['save-date'] = array(
			'href' => '#',
			'desc' => $this->l('Generate PDF file by date')
		);

		$this->toolbar_btn['save-status'] = array(
			'href' => '#',
			'desc' => $this->l('Generate PDF file by status')
		);
	}

	public function postProcess()
	{
		if (Tools::getValue('submitAddinvoice_date'))
		{
			if (!Validate::isDate(Tools::getValue('date_from')))
				$this->_errors[] = $this->l('Invalid from date');

			if (!Validate::isDate(Tools::getValue('date_to')))
				$this->_errors[] = $this->l('Invalid end date');

			if (!count($this->_errors))
			{
				$order_invoice_list = OrderInvoice::getByDateInterval(Tools::getValue('date_from'), Tools::getValue('date_to'));

				if (count($order_invoice_list))
					Tools::redirectAdmin('pdf.php?invoices&date_from='.urlencode(Tools::getValue('date_from')).'&date_to='.urlencode(Tools::getValue('date_to')).'&token='.$this->token);

				$this->_errors[] = $this->l('No invoice found for this period');
			}
		}
		else if (Tools::isSubmit('submitAddinvoice_status'))
		{
			if (!is_array($status_array = Tools::getValue('id_order_state')) || !count($status_array))
				$this->_errors[] = $this->l('Invalid order statuses');
			else
			{
				foreach ($status_array as $id_order_state)
					if (count($orders = Order::getOrderIdsByStatus((int)$id_order_state)))
						Tools::redirectAdmin('pdf.php?invoices2&id_order_state='.implode('-', $status_array).'&token='.$this->token);

				$this->_errors[] = $this->l('No invoice found for this status');
			}
		}
		else
			parent::postProcess();
	}

	public function beforeUpdateOptions()
	{
		if ((int)Tools::getValue('PS_INVOICE_START_NUMBER') != 0 && (int)Tools::getValue('PS_INVOICE_START_NUMBER') <= Order::getLastInvoiceNumber())
				$this->_errors[] = $this->l('Invalid invoice number (must be > ').Order::getLastInvoiceNumber().')';
	}
}

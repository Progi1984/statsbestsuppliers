<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class statsbestsuppliers extends ModuleGrid
{
    private $html = null;
    private $query = null;
    private $columns = null;
    private $default_sort_column = null;
    private $default_sort_direction = null;
    private $empty_message = null;
    private $paging_message = null;

    public function __construct()
    {
        $this->name = 'statsbestsuppliers';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();

        $this->default_sort_column = 'sales';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->trans('Empty recordset returned', array(), 'Admin.Notifications.Info');
        $this->paging_message = $this->trans('Displaying %1$s of %2$s', array('{0} - {1}', '{2}'), 'Admin.Global');

        $this->columns = array(
            array(
                'id' => 'name',
                'header' => $this->trans('Name', array(), 'Admin.Global'),
                'dataIndex' => 'name',
                'align' => 'center'
            ),
            array(
                'id' => 'quantity',
                'header' => $this->trans('Quantity sold', array(), 'Admin.Global'),
                'dataIndex' => 'quantity',
                'align' => 'center'
            ),
            array(
                'id' => 'sales',
                'header' => $this->trans('Total paid', array(), 'Modules.Statsbestsuppliers.Admin'),
                'dataIndex' => 'sales',
                'align' => 'center'
            )
        );

        $this->displayName = $this->trans('Best suppliers', array(), 'Modules.Statsbestsuppliers.Admin');
        $this->description = $this->trans('Enrich your stats, add a list of the best suppliers to the dashboard.', array(), 'Modules.Statsbestsuppliers.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('displayAdminStatsModules'));
    }

    public function hookDisplayAdminStatsModules($params)
    {
        $engine_params = array(
            'id' => 'id_category',
            'title' => $this->displayName,
            'columns' => $this->columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message
        );

        if (Tools::getValue('export') == 1) {
            $this->csvExport($engine_params);
        }
        $this->html = '
			<div class="panel-heading">
				'.$this->displayName.'
			</div>
			'.$this->engine($engine_params).'
			<a class="btn btn-default export-csv" href="'.Tools::safeOutput($_SERVER['REQUEST_URI'].'&export=1').'">
				<i class="icon-cloud-upload"></i> '.$this->trans('CSV Export', array(), 'Admin.Global').'
			</a>';
        return $this->html;
    }

    /**
     * @return int Get total of distinct suppliers
     */
    public function getTotalCount()
    {
        $sql = 'SELECT COUNT(DISTINCT(s.id_supplier))
				FROM '._DB_PREFIX_.'order_detail od
				LEFT JOIN '._DB_PREFIX_.'product p ON p.id_product = od.product_id
				LEFT JOIN '._DB_PREFIX_.'orders o ON o.id_order = od.id_order
				LEFT JOIN '._DB_PREFIX_.'supplier s ON s.id_supplier = p.id_supplier
				WHERE o.invoice_date BETWEEN '.$this->getDate().'
					'.Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o').'
					AND o.valid = 1
					AND s.id_supplier IS NOT NULL';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    public function getData()
    {
        $this->_totalCount = $this->getTotalCount();

        $this->query = 'SELECT s.name, SUM(od.product_quantity) as quantity, ROUND(SUM(od.product_quantity * od.unit_price_tax_excl) / o.conversion_rate, 2) as sales
				FROM '._DB_PREFIX_.'order_detail od
				LEFT JOIN '._DB_PREFIX_.'product p ON p.id_product = od.product_id
				LEFT JOIN '._DB_PREFIX_.'orders o ON o.id_order = od.id_order
				LEFT JOIN '._DB_PREFIX_.'supplier s ON s.id_supplier = p.id_supplier
				WHERE o.invoice_date BETWEEN '.$this->getDate().'
					'.Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o').'
					AND o.valid = 1
					AND s.id_supplier IS NOT NULL
				GROUP BY p.id_supplier';
        if (Validate::IsName($this->_sort)) {
            $this->query .= ' ORDER BY `'.$this->_sort.'`';
            if (isset($this->_direction) && Validate::isSortDirection($this->_direction)) {
                $this->query .= ' '.$this->_direction;
            }
        }

        if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT '.$this->_start.', '.($this->_limit);
        }
        $this->_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
    }
}

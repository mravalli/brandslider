<?php
/**
 * 2007-2020 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Adapter\Validate;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_'))
	exit ;

class BrandSlider extends Module implements WidgetInterface
{
    /** @var string */
    public $secure_key;
    
    protected $_html = '';
    protected $default_speed = 5000;
    protected $default_pause_on_hover = 1;
    protected $default_wrap = 1;
    protected $templateFile = 'module:brandslider/views/templates/hook/slider.tpl';
    
	private $user_groups;
	private $pattern = '/^([A-Z_]*)[0-9]+/';
	private $page_name = '';
	private $spacer_size = '5';
	private $_postErrors = array();
    
    public function __construct() {
		$this->name = 'brandslider';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'neuro3.com';
		$this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.4.0', 'max' => '1.7.9'];
        //$this->secure_key = Tools::hash($this->name);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Brand Slider');
		$this->description = $this->l('Displays a block of manufacturers/brands');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        if (!Configuration::get('brandslider_hook')) {
            $this->warning = $this->l('No name provided');
        }
	}

    /** @see Module::install() */
	public function install() {
        $this->clearCache();
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (
            parent::install() &&
            $this->registerHook('displayHome') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayAdditionalFooter') &&
            Configuration::updateValue('brandslider_hook', 1)
        ) {
            $shops = Shop::getContextListShopID();
            $shop_groups_list = [];
            $res = true;

            /* Setup each shop */
            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                /* Sets up configuration */
                $res &= Configuration::updateValue('BRANDSLIDER_SPEED', $this->default_speed, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('BRANDSLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('BRANDSLIDER_WRAP', $this->default_wrap, false, $shop_group_id, $shop_id);
            }

            /* Sets up Shop Group configuration */
            if (count($shop_groups_list)) {
                foreach ($shop_groups_list as $shop_group_id) {
                    $res &= Configuration::updateValue('BRANDSLIDER_SPEED', $this->default_speed, false, $shop_group_id);
                    $res &= Configuration::updateValue('BRANDSLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover, false, $shop_group_id);
                    $res &= Configuration::updateValue('BRANDSLIDER_WRAP', $this->default_wrap, false, $shop_group_id);
                }
            }

            /* Sets up Global configuration */
            $res &= Configuration::updateValue('BRANDSLIDER_SPEED', $this->default_speed);
            $res &= Configuration::updateValue('BRANDSLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover);
            $res &= Configuration::updateValue('BRANDSLIDER_WRAP', $this->default_wrap);
        }

        $this->disableDevice(Context::DEVICE_MOBILE);

        return (bool) $res;
	}

    public function uninstall()
    {
        return (
            parent::uninstall()
            && Configuration::deleteByName('brandslider_hook')
        );
    }
	
	public function getContent() {
		if (Tools::isSubmit('submitModule')) {
			$this->clearCache();
			
			Configuration::updateValue('brandslider_hook', (int)Tools::getValue('brandslider_hook'));

			$items = Tools::getValue('items');
			if (
                !(is_array($items) &&
                    count($items) &&
                    Configuration::updateValue('manufactuterslider_id', implode(',', $items)))
            ) {
                $errors[] =$this->l('Unable to update settings.');
            }

			$this->clearCache();

			if (isset($errors) AND sizeof($errors))
				$this->_html .= $this->displayError(implode('<br />', $errors));
			else
				$this->_html .= $this->displayConfirmation($this->l('Settings updated'));

		}

		$this->_html .= $this->renderForm();
		return $this->_html;
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Menu Top Link'),
					'icon' => 'icon-link'
					),
				'input' => array(
					array(
						'type' => 'select',
						'label' => $this->l('Module hook'),
						'name' => 'brandslider_hook',
						'options' => array(
							'query' => array(array(
								'id_option' => 1,
								'name' => $this->l('displayHome')
								),
							array(
								'id_option' => 0,
								'name' => $this->l('displayAdditionalFooter')
								)
							),                           
							'id' => 'id_option',                           
							'name' => 'name'
							)
						),
					array(
						'type' => 'link_choice',
						'label' => '',
						'name' => 'link',
						'lang' => true,
						),	
					),

				'submit' => array(
					'name' => 'submitModule',
					'title' => $this->l('Save')
					)
				),
			);
		
		if (Shop::isFeatureActive())
			$fields_form['form']['description'] = $this->l('The modifications will be applied to').' '.(Shop::getContext() == Shop::CONTEXT_SHOP ? $this->l('shop').' '.$this->context->shop->name : $this->l('all shops'));
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->module = $this;
		$helper->identifier = $this->identifier;		
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
            'language' => [
                'id_lang' => $lang->id,
                'iso_code' => $lang->iso_code,
            ],
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
			'choices' => $this->renderChoicesSelect(),
			'fields_value' => $this->getConfigFieldsValues(),
			'selected_links' => $this->makeMenuOption(),
			);
		return $helper->generateForm(array($fields_form));
	}

	public function renderChoicesSelect()
	{
		$spacer = str_repeat('&nbsp;', $this->spacer_size);
		$items = $this->getMenuItems();
		
		$html = '<select multiple="multiple" id="availableItems" style="width: 300px; height: 160px;">';

		// BEGIN Manufacturer
		$html .= '<optgroup label="'.$this->l('Manufacturer').'">';
		$manufacturers = Manufacturer::getManufacturers(false, $this->context->language->id);
		foreach ($manufacturers as $manufacturer)
			if (!in_array($manufacturer['id_manufacturer'], $items))
				$html .= '<option value="'.$manufacturer['id_manufacturer'].'">'.$spacer.$manufacturer['name'].'</option>';
			$html .= '</optgroup>';

			$html .= '</select>';
			return $html;
		}

    private function makeMenuOption()
    {
        $menu_item = $this->getMenuItems();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)Shop::getContextShopID();
        $html = '<select multiple="multiple" name="items[]" id="items" style="width: 300px; height: 160px;">';
        foreach ($menu_item as $item)
        {
            if (!$item)
                continue;
            preg_match($this->pattern, $item, $values);
            $id = (int)substr($item, strlen($values[1]), strlen($item));


            $manufacturer = new Manufacturer((int)$id, (int)$id_lang);
            if (Validate::isLoadedObject($manufacturer))
                $html .= '<option selected="selected" value="'.$id.'">'.$manufacturer->name.'</option>'.PHP_EOL;

        }
        return $html.'</select>';
    }

    private function getMenuItems()
    {

        $conf = Configuration::get('manufactuterslider_id');
        if (strlen($conf))
            return explode(',', Configuration::get('manufactuterslider_id'));
        else
            return array();

    }

    private function prepareHook($params)
    {
        if (!$this->isCached('slider.tpl', $this->getCacheId())) {
            $m_id = (Configuration::get('manufactuterslider_id'));
            $m_ids = explode(',', $m_id);
            $id_lang = (int)$this->context->language->id;
            $manufacturers = array();

            foreach ($m_ids as  $item) {
                if (!$item)
                    continue;
                $id = $item;

                $manufacturer = new Manufacturer((int)$id, (int)$id_lang);
                if (Validate::isLoadedObject($manufacturer)){
                    $manufacturers[$item]['id_manufacturer'] = $item;
                    $manufacturers[$item]['name'] = $manufacturer->name;
                    $manufacturers[$item]['link_rewrite'] = $manufacturer-> link_rewrite;
                }
            }

            $this->smarty->assign('manufacturers', $manufacturers);
            $this->smarty->assign('manufacturerSize', Image::getSize('mf_image'));
        }
        return $this->display(__FILE__, 'slider.tpl', $this->getCacheId());
    }

    public function hookHeader($params)
    {
        $this->context->controller->addCSS(($this->_path) . 'assets/slick.css', 'all');
        $this->context->controller->addCSS(($this->_path) . 'assets/brandslider.css', 'all');
        $this->context->controller->addJS(($this->_path) . 'assets/slick.min.js');
        $this->context->controller->addJS(($this->_path) . 'assets/brandslider.js');
    }

    public function hookActionObjectManufacturerUpdateAfter($params)
    {
        $this->clearCache();
    }

    public function hookActionObjectManufacturerAddAfter($params)
    {
        $this->clearCache();
    }

    public function hookActionObjectManufacturerDeleteAfter($params)
    {
        $this->clearCache();
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId())) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->fetch($this->templateFile, $this->getCacheId());
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $slides = $this->getSlides(true);
        if (is_array($slides)) {
            foreach ($slides as &$slide) {
                $slide['sizes'] = @getimagesize(
                    (__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $slide['image'])
                );
                if (isset($slide['sizes'][3]) && $slide['sizes'][3]) {
                    $slide['size'] = $slide['sizes'][3];
                }
            }
        }

        $config = $this->getConfigFieldsValues();

        return [
            'brandslider' => [
                'speed' => $config['BRANDSLIDER_SPEED'],
                'pause' => $config['BRANDSLIDER_PAUSE_ON_HOVER'] ? 'hover' : '',
                'wrap' => $config['BRANDSLIDER_WRAP'] ? 'true' : 'false',
                'slides' => $slides,
            ],
        ];
    }

    public function getConfigFieldsValues()
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();

        return [
            'BRANDSLIDER_SPEED' => Tools::getValue('HOMESLIDER_SPEED', Configuration::get('HOMESLIDER_SPEED', null, $id_shop_group, $id_shop)),
            'BRANDSLIDER_PAUSE_ON_HOVER' => Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER', Configuration::get('HOMESLIDER_PAUSE_ON_HOVER', null, $id_shop_group, $id_shop)),
            'BRANDSLIDER_WRAP' => Tools::getValue('HOMESLIDER_WRAP', Configuration::get('HOMESLIDER_WRAP', null, $id_shop_group, $id_shop)),
        ];
    }

    private function getSlides()
    {
        $m_id = (Configuration::get('manufactuterslider_id'));
        $m_ids = explode(',', $m_id);
        $id_lang = (int)$this->context->language->id;
        $manufacturers = array();

        foreach ($m_ids as  $item) {
            if (!$item)
                continue;
            $id = $item;

            $manufacturer = new Manufacturer((int)$id, $id_lang);
            if (Validate::isLoadedObject($manufacturer)){
                $manufacturers[$item]['image_url'] = $this->context->link->getMediaLink(
                    _PS_IMG_ . 'm/' . $id . '-small_default.jpg'
                );
                $manufacturers[$item]['url'] = "/{$id}_{$manufacturer->link_rewrite}";
                $manufacturers[$item]['legend'] = $manufacturer->name;
            }
        }

        return $manufacturers;
    }

    private function clearCache()
    {
        $this->_clearCache($this->templateFile);
    }
}

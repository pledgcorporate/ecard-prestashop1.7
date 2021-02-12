<?php

/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
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
*  @author Ginidev <gildas@ginidev.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PaymentModule;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use Symfony\Component\Form\Extension\Core\Type\TextType;

require_once _PS_MODULE_DIR_ . '/pledg/class/Pledgpaiements.php';
require_once _PS_MODULE_DIR_ . '/pledg/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Pledg extends PaymentModule{

    const PLEDG_REFERENCE_PREFIXE = 'PLEDG_';

    /**
     * Pledg constructor.
     */
    public function __construct(){
        $this->name = 'pledg';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.0';
        $this->author = 'LucasFougeras';
        $this->controllers = array('payment', 'validation', 'notification');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;        
        $this->displayName = $this->l('Pledg - Split the payment');
        $this->description = $this->l('This module allows you to accept payments by pledg.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete these details?');
        $this->ps_versions_compliancy = array('min' => '1.7.7', 'max' => _PS_VERSION_);
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        parent::__construct();
    }


    /**
     * Method Installer
     * @return bool
     */
    public function install(){

        return parent::install()
        && $this->_installTab()
        && $this->_installSql()
        && $this->_installConfiguration()
        && $this->registerHook('payment')
        && $this->registerHook('paymentOptions')
        && $this->registerHook('paymentReturn')
        && $this->registerHook('actionFrontControllerSetMedia')
        && $this->registerHook('actionOrderGridDefinitionModifier')
        && $this->registerHook('actionOrderGridQueryBuilderModifier')
        && $this->registerHook('displayAdminOrderTabContent');
    }

    protected function _installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminPledg';
        $tab->module = $this->name;
        $tab->id_parent = (int)Tab::getIdFromClassName('DEFAULT');
        $tab->icon = 'credit_card';
        $languages = Language::getLanguages();
        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Pledg - Paiements');
        }
        try {
            $tab->save();
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
 
        return true;
    }

    protected function _installSql()
    {
        $sqlCreate1 = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "pledg_paiements` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `status` int(11) NULL,
                `mode` int(11) NULL,
                `position` int(11) NULL,
                `merchant_id` varchar(255) NULL,
                `secret` varchar(255) NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";
        $sqlCreate2 = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "pledg_paiements_confirm` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `id_cart` int(11) NULL,
                `reference_pledg` varchar(255) NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        // UPDATE TABLE TO ADD ICON, PRIORITY, MIN AND MAX FIELDS
        $sqlCreate3 = "
            ALTER TABLE `" . _DB_PREFIX_ . "pledg_paiements`
            ADD `min` int(11) NULL DEFAULT NULL AFTER `secret` ;";
        $sqlCreate4 = "
            ALTER TABLE `" . _DB_PREFIX_ . "pledg_paiements`
            ADD `max` int(11) NULL DEFAULT NULL AFTER `secret`;";
        $sqlCreate5 = "
            ALTER TABLE `" . _DB_PREFIX_ . "pledg_paiements`
            ADD `icon` VARCHAR(512) NULL DEFAULT NULL AFTER `max`;";
        $sqlCreate6 = "
            ALTER TABLE `" . _DB_PREFIX_ . "pledg_paiements`
            ADD `shops` VARCHAR(512) NULL DEFAULT NULL AFTER `icon`;";

        $sqlCreateLang = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "pledg_paiements_lang` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `id_lang` int(11) NOT NULL,
              `title` varchar(255) NULL,
              `description` text,
              PRIMARY KEY (`id`,`id_lang`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        Db::getInstance()->execute($sqlCreate1);
        Db::getInstance()->execute($sqlCreate2);
        Db::getInstance()->execute($sqlCreate3);
        Db::getInstance()->execute($sqlCreate4);
        Db::getInstance()->execute($sqlCreate5);
        Db::getInstance()->execute($sqlCreate6);
        Db::getInstance()->execute($sqlCreateLang);
        return true;
    }

    // Install the PLEDG order state (waiting for pledg notification)
    protected function _installConfiguration(){
        // Create ID & Color #4169E1
        $stateId = (int) \Configuration::getGlobalValue("PLEDG_STATE_WAITING_NOTIFICATION");
        // Is state ID already existing in the Configuration table ?
        if (0 === $stateId || false === \OrderState::existsInDatabase($stateId, "order_state")) {
            $data = [
                'module_name' => $this->name,
                'color' => "#4169E1",
                'unremovable' => 1,
            ];
            if (true === \Db::getInstance()->insert("order_state", $data)) {
                $stateId = (int) \Db::getInstance()->Insert_ID();
                \Configuration::updateGlobalValue("PLEDG_STATE_WAITING_NOTIFICATION", $stateId);
            }
        }

        // Create traductions
        $languagesList = \Language::getLanguages();
        $trad = array(
            'en' => 'Waiting for Pledg payment notification',
            'fr' => 'En attente de la notification de paiement par Pledg',
            'es' => 'A la espera de la notificación de pago de Pledg',
            'it' => 'In attesa della notifica di pagamento Pledg',
            'nl' => 'Wachten op Pledg betalings notificatie',
            'de' => 'Warten auf Pledg-Zahlung',
            'pl' => 'Oczekiwanie na powiadomienie o płatności Pledg',
            'pt' => 'Aguardando a notificação de pagamento Pledg',
        );

        foreach ($languagesList as $key => $lang) {
            if (true === $this->stateLangAlreadyExists($stateId, (int) $lang['id_lang'])) {
                continue;
            }
            $statesTranslation = isset($trad[$lang['iso_code']])? $trad[$lang['iso_code']] : $trad['en'];
            $this->insertNewStateLang($stateId, $statesTranslation, (int) $lang['id_lang']);
        }
        $this->setStateIcons($stateId);
        return true;
    }

    /**
     * Check if Pledg State language already exists in the table ORDER_STATE_LANG_TABLE (from Paypal module)
     *
     * @param int $orderStateId
     * @param int $langId
     *
     * @return bool
     */
    private function stateLangAlreadyExists($orderStateId, $langId)
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT id_order_state
            FROM  `' . _DB_PREFIX_ . 'order_state_lang`
            WHERE
                id_order_state = ' . $orderStateId . '
                AND id_lang = ' . $langId
        );
    }

    /**
     * Create the Pledg States Lang (from Paypal module)
     *
     * @param int $orderStateId
     * @param string $translations
     * @param int $langId
     *
     * @throws PsCheckoutException
     * @throws \PrestaShopDatabaseException
     */
    private function insertNewStateLang($orderStateId, $translations, $langId)
    {
        $data = [
            'id_order_state' => $orderStateId,
            'id_lang' => (int) $langId,
            'name' => pSQL($translations),
            'template' => "payment",
        ];
        return false === \Db::getInstance()->insert("order_state_lang", $data);
    }

    /**
     * Set an icon for the current State Id (from Paypal module)
     *
     * @param string $state
     * @param int $orderStateId
     *
     * @return bool
     */
    private function setStateIcons($orderStateId)
    {
        $iconExtension = '.gif';
        $iconToPaste = _PS_ORDER_STATE_IMG_DIR_ . $orderStateId . $iconExtension;

        if (true === file_exists($iconToPaste)) {
            if (true !== is_writable($iconToPaste)) {
                return false;
            }
        }
        $iconName = 'waiting';
        $iconsFolderOrigin = _PS_MODULE_DIR_ . $this->name . '/views/img/';
        $iconToCopy = $iconsFolderOrigin . $iconName . $iconExtension;

        if (false === copy($iconToCopy, $iconToPaste)) {
            return false;
        }
        return true;
    }


    public function uninstall(){
        return $this->_uninstallSql()
            && $this->_uninstallTab()
            && parent::uninstall();

    }

    protected function _uninstallTab(){
        $idTab = (int)Tab::getIdFromClassName('AdminPledg');
        if ($idTab) {
            $tab = new Tab($idTab);
            try {
                $tab->delete();
            } catch (Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }
        return true;
    }

    protected function _uninstallSql(){
        return true;
    }

    public function hookActionFrontControllerSetMedia(){
        $this->context->controller->registerJavascript('pledgjs', 'modules/'.$this->name.'/assets/js/pledg.js');
        $this->context->controller->registerStylesheet('pledgcss', 'modules/'.$this->name.'/assets/css/pledg.css');
    }

    /**
     * hookPaymentOptions
     *
     * @param $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return;
        }

        $total = str_replace('.', '', number_format($params['cart']->getOrderTotal(), 2, '.', ''));

        $payment_options = [];

        $sql = 'SELECT p.id, p.merchant_id, p.mode, p.min, p.max, p.position, p.shops, pl.title, pl.description, p.secret, p.icon
                FROM ' . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . ' AS p 
                LEFT JOIN ' . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . '_lang AS pl ON pl.id = p.id
                WHERE p.status = 1 AND pl.id_lang = ' . $this->context->language->id
                .' ORDER BY p.position ASC';

        if ($results = Db::getInstance()->ExecuteS($sql)) {

            foreach ($results as $result) {

                // We check min and max
                if(($result['max'] > 0 && $total > $result['max']*100) || ($result['min'] >0 && $total < $result['min']*100)){
                    continue;
                }
                // We check that the current shop is not disabled
                $currentShop = $this->context->shop->id;
                $shops =  explode(',',$result['shops']);
                if(in_array($currentShop, $shops)){
                    continue;
                }

                $urlApi = [ 'payload'=>[
                    'created' => date("Y-m-d"),
                    'amount_cents' => intval($total)
                ]];
                $urlApi['url'] = (($result['mode'])? 'https://back.ecard.pledg.co/api/users/me/merchants/' : 'https://staging.back.ecard.pledg.co/api/users/me/merchants/' );
                $urlApi['url'] .= $result['merchant_id'];
                $urlApi['url'] .="/simulate_payment_schedule";

                $this->context->smarty->assign([
                    'description' => $result['description'],
                    'url_api' => json_encode($urlApi),
                    'payment_detail_trad' => json_encode($this->payment_detail_trad(substr($this->context->language->locale, 0, 2), $this->context->currency->symbol)),
                    'pledg_locale' => $this->context->language->locale,
                ]);

                $newOption = new PaymentOption();
                $newOption->setModuleName($result['title']);
                $newOption->setCallToActionText($result['title']);
                $newOption->setBinary(false);
                $newOption->setAction($this->context->link->getModuleLink($this->name, 'iframe', array(), true));
                $newOption->setAdditionalInformation($this->fetch('module:pledg/views/templates/front/payment_infos.tpl'));
                if($result['icon']){$newOption->setLogo('modules'.$result['icon']);}
                $newOption->setInputs([
                    'pledgId' => [
                        'name' => 'pledgId',
                        'type' => 'hidden',
                        'value' => $result['id'],
                    ],
                ]);

                array_push($payment_options, $newOption);
            }
        }

        return $payment_options;

    }

    public function payment_detail_trad($lang, $currency="€"){
		$availableLangs = ['en', 'fr'];
		if(!in_array($lang, $availableLangs)){
			$lang = $availableLangs[0];
		}
		$traductions = [
			'en' => [
				'currencySign' => 'before',
				'deadline' => 'Deadline',
				'the' => 'the',
				'fees' => '(including %s of fees)',
				'deferred' => 'I\'ll pay %s1 on %s2.',
			],
			'fr' => [
				'currencySign' => 'after',
                'deadline' => 'Echéance',
				'the' => 'le',
				'fees' => '(dont %s de frais)',
				'deferred' => 'Je paierai %s1 le %s2.',
			],
		];
        $ret = $traductions[$lang];
        $ret['currency'] = $currency;
	    return $ret;
    }

    public function checkCurrency($cart){
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
        
    public function hookPaymentReturn($params){
        if (!$this->active) {
            return;
        }
        $order = $params['order'];
        $currencyIso = Currency::getIsoCodeById($order->id_currency);
        $priceConverted = $this->context->currentLocale->formatPrice($order->getOrdersTotalPaid(), $currencyIso);
        $this->smarty->assign(array(
            'total_to_pay' => $priceConverted,
            'status' => 'ok',
            'id_order' => $order->id
        ));
        if (isset($order->reference) && !empty($order->reference)) {
            $this->smarty->assign('reference', $order->reference);
        }
        return $this->display(__FILE__, 'payment_return.tpl');
    }

    public function hookActionOrderGridDefinitionModifier($params) {
        $definition = $params['definition'];
        $definition
        ->getColumns()
        ->addAfter(
            'id_order',
            (new DataColumn('pledg_ref'))
                ->setName($this->l('Reference PLEDG'))
                ->setOptions([
                    'field' => 'pledg_ref',
                ])
        );
        $definition->getFilters()
            ->add((new Filter('pledg_ref', TextType::class))
            ->setTypeOptions([
                'attr' => [
                    'placeholder' => $this->l('Pledg Reference'),
                ],
                'required' => false,
            ])
            ->setAssociatedColumn('pledg_ref')
        );
    }
    public function hookActionOrderGridQueryBuilderModifier($params) {
        $searchQueryBuilder = $params['search_query_builder'];
        $searchCriteria = $params['search_criteria'];
        $searchQueryBuilder->addSelect('(
            SELECT
              pledg.reference_pledg
            FROM
            `'._DB_PREFIX_.'pledg_paiements_confirm`
              LIMIT 1
        ) as pledg_ref');
        $searchQueryBuilder->leftJoin('o', _DB_PREFIX_.'pledg_paiements_confirm', 'pledg', 'pledg.`id_cart` = o.`id_cart`');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('pledg_ref' === $filterName) {
                $searchQueryBuilder->andWhere('pledg.reference_pledg LIKE :pledg_ref_');
                $searchQueryBuilder->setParameter('pledg_ref_', '%'.$filterValue.'%');
            }
        }
    }

    public function hookDisplayAdminOrderTabContent($params) {
        $order = new Order($params['id_order']);
        require_once _PS_MODULE_DIR_ . 'pledg/class/PledgpaiementsConfirm.php';
        $pledgPaimentConfirm = new PledgpaiementsConfirm(PledgpaiementsConfirm::getByIdCart($order->id_cart));
        return '<span class="badge rounded badge-dark">' . $this->l('Pledg Reference : '). $pledgPaimentConfirm->reference_pledg . '</span>';
    }

    /**
     *  Function to create metadata
     */
    public function create_metadata() {
        $metadata = [];
        $metadata['plugin'] = 'prestashop1.6-pledg-plugin_v' . $this->version ;
        $metadata['departure-date'] = date('Y-m-d');
        $summaryDetails = $this->context->cart->getSummaryDetails();
		try
		{
            $products = $summaryDetails['products'];
            $md_products = [];
            foreach ($products as $key_product => $product) {
                $md_product = [];
                $md_product['id_product'] = $product['id_product'];
                $md_product['reference'] = $product['reference'];
				$md_product['type'] = $product['is_virtual'] == "0" ? 'physical' : 'virtual';
				$md_product['quantity'] = $product['quantity'] ;
				$md_product['name'] = $product['name'];
				$md_product['unit_amount_cents'] = intval($product['price_wt']*100);
				$md_product['category'] = $product['category'];
				array_push($md_products, $md_product);
            }
            $metadata['delivery_mode'] = $summaryDetails['carrier']->name;
            $metadata['delivery_speed'] = $summaryDetails['carrier']->delay;
            $metadata['delivery_label'] = $summaryDetails['carrier']->name;
            $metadata['delivery_cost'] = intval($summaryDetails['total_shipping_tax_exc']*100);
            $metadata['delivery_tax_cost'] = intval($summaryDetails['total_shipping']*100);
			$metadata['products'] = $md_products;
		}
		catch (Exception $exp) {
            Logger::addLog(sprintf($this->l('pledg_create_metadata exception : %s'),($exp->getMessage())), 3);
        }
		return $metadata;
	}
}
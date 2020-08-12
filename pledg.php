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



*  @copyright  2007-2015 PrestaShop SA



*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)



*  International Registered Trademark & Property of PrestaShop SA



*/



require_once _PS_MODULE_DIR_ . '/pledg/class/Pledgpaiements.php';



if (!defined('_PS_VERSION_')) {

    exit;

}



use PrestaShop\PrestaShop\Core\Payment\PaymentOption;



class Pledg extends PaymentModule{



    private $html = '';

    private $postErrors = array();

    public $serviceID;

    public $secretKey;

    public $gateway='https://www.fastpay.com/pay';



    public function __construct(){



        $this->name = 'pledg';

        $this->tab = 'payments_gateways';

        $this->version = '1.0.0';

        $this->author = 'Web Ice';

        $this->controllers = array('payment', 'validation');

        $this->currencies = true;

        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;        

        $this->displayName = $this->l('Pledg');

        $this->description = $this->l('This module allows you to accept payments by pledg.');

        $this->confirmUninstall = $this->l('Are you sure you want to delete these details?');

        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);



        if (!count(Currency::checkPaymentCurrencies($this->id))) {

            $this->warning = $this->l('No currency has been set for this module.');

        }



        parent::__construct();



    }







    public function install(){



        return parent::install()

        && $this->_installTab()

        && $this->_installSql()

        && $this->registerHook('paymentOptions')

        && $this->registerHook('paymentReturn')

        && $this->registerHook('displayPaymentByBinaries');



    }



    protected function _installTab()

    {

        $tab = new Tab();

        $tab->class_name = 'AdminPledg';

        $tab->module = $this->name;

        $tab->id_parent = (int)Tab::getIdFromClassName('DEFAULT');

        $tab->icon = 'settings_applications';

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

        $sqlCreate = "CREATE TABLE `" . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . "` (

                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,

                `id_payment` int(11) DEFAULT NULL,

                `status` int(11) DEFAULT NULL,

                `position` int(11) DEFAULT NULL,

                `mode` int(11) DEFAULT NULL,

                `merchant_id` varchar(255) DEFAULT NULL,

                `date_add` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

                `date_upd` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`)

                ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

 

        $sqlCreateLang = "CREATE TABLE `" . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . "_lang` (

              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,

              `id_lang` int(11) NOT NULL,

              `title` varchar(255) DEFAULT NULL,

              `description` text,

              PRIMARY KEY (`id`,`id_lang`)

            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

 

        return Db::getInstance()->execute($sqlCreate) && Db::getInstance()->execute($sqlCreateLang);

    }



    public function uninstall(){

        return $this->_uninstallSql()

            && $this->_uninstallTab()

            && parent::uninstall();

    }



    protected function _uninstallTab()

    {

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

        $sql = "DROP TABLE ". _DB_PREFIX_ .Pledgpaiements::$definition['table'].",". _DB_PREFIX_ .Pledgpaiements::$definition['table']."_lang";

        return Db::getInstance()->execute($sql);

    }

    public function hookPaymentOptions($params){

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [];

        $sql = 'SELECT p.id_payment, p.merchant_id, p.mode, pl.title, pl.description
                FROM '. _DB_PREFIX_ .Pledgpaiements::$definition['table'] . ' AS p 
                LEFT JOIN '. _DB_PREFIX_ .Pledgpaiements::$definition['table'] . '_lang AS pl ON pl.id = p.id
                WHERE p.status = 1 AND pl.id_lang = ' . $this->context->language->id;

        if ($results = Db::getInstance()->ExecuteS($sql)):

            $cart = $this->context->cart;
            $id_customer = $cart->id_customer;
            $customer = New Customer($id_customer);

            $products = $cart->getProducts();

            $title = array();
            foreach ($products as $product):
                array_push($title, $product['name']);
            endforeach;

            $amountCents = str_replace('.', '', number_format($cart->getOrderTotal(), 2, '.', ''));

            $id_address_delivery = $cart->id_address_delivery;
            $address = new Address($id_address_delivery);

            $id_country = $address->id_country;
            $country_iso_code = Country::getIsoById($id_country);

            foreach($results as $result):

                $this->context->smarty->assign([
                    'id_payment' => $result['id_payment'],
                    'description' => $result['description'],
                    'merchantUid' => $result['merchant_id'],
                    'amountCents' => $amountCents,
                    'email' => $customer->email,
                    'title' => ( ($title)? implode(', ', $title) : '' ),
                    'reference' => 'order_' . $cart->id,
                    'firstName' => $customer->firstname,
                    'lastName' =>  $customer->lastname,
                    'street' => $address->address1,
                    'city' => $address->city,
                    'zipcode' => $address->postcode,
                    'stateProvince' => '',
                    'country' => $country_iso_code

                ]);

                $newOption = new PaymentOption();
                $newOption->setModuleName($result['title']);
                $newOption->setCallToActionText($result['title']);
                $newOption->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));
                $newOption->setAdditionalInformation($this->fetch('module:pledg/views/templates/front/payment_infos.tpl'));
                $newOption->setInputs([
                    'token' => [
                        'name' =>'token',
                        'type' =>'hidden',
                        'value' => '',
                    ],
                ]);
                array_push($payment_options, $newOption);

            endforeach;

        endif;  

        return $payment_options;

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

}
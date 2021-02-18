<?php

require_once _PS_MODULE_DIR_ . '/pledg/pledg.php';
require_once _PS_MODULE_DIR_ . '/pledg/vendor/autoload.php';

class PledgIframeModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (!$this->module->active) {
            return;
        }

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            return;
        }

        // Title
        $products = $cart->getProducts();
        $title = array();
        foreach ($products as $product) {
            array_push($title, $product['name']);
        }

        // Customer
        $id_customer = $cart->id_customer;
        $customer = New Customer($id_customer);

		$total = str_replace('.', '', number_format($cart->getOrderTotal(), 2, '.', ''));
        $id_address_delivery = $cart->id_address_delivery;
		$id_address_invoice = $cart->id_address_invoice;
        $address = new Address($id_address_delivery);
		$address_invoice = new Address($id_address_invoice);
        $id_country = $address->id_country;
		$id_country_invoice = $address_invoice->id_country;
        $country_iso_code = Country::getIsoById($id_country);
		$country_iso_code_invoice = Country::getIsoById($id_country_invoice);

        // Currency
        $currency = New Currency($cart->id_currency);

        // Phone E164 Conversion
        $phone = $address->phone_mobile != '' ? $address->phone_mobile : $address->phone;
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $phoneNumber = $phoneUtil->parse($phone, $country_iso_code);
            $phone = $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
        } catch (\libphonenumber\NumberParseException $e) {
            Logger::addLog(sprintf($this->module->l('Pledg Payment Phone Number Parse error : %s'),($phone)));
            $phone = '';
        }

        $pledgId = $_POST['pledgId'];
        $sql = 'SELECT p.id, p.merchant_id, p.mode, p.min, p.max, p.position, p.shops, pl.title, pl.description, p.secret, p.icon
                FROM ' . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . ' AS p 
                LEFT JOIN ' . _DB_PREFIX_ . Pledgpaiements::$definition['table'] . '_lang AS pl ON pl.id = p.id
                WHERE p.status = 1 AND pl.id_lang = ' . $this->context->language->id . ' AND p.id = ' . $pledgId
                .' ORDER BY p.position DESC LIMIT 0,1';

        $result = Db::getInstance()->ExecuteS($sql)[0];

        $max = $result['max'];
        $min = $result['min'];
        if(($max > 0 && $total > $max*100) || ($min >0 && $total < $min*100)){
            return;
        }
        
        $forbiddenShops = $result['shops'];
        $currentShop = $this->context->shop->id;
        $shops =  explode(',',$forbiddenShops);
        if(in_array($currentShop, $shops)){
            return;
        }

        $metadata = $this->module->create_metadata();

        $paramsPledg = array(
            'id' => $result['id'],
            'titlePayment' => $result['title'],
            'icon' => $result['icon'] != '' && file_exists(_PS_MODULE_DIR_ . $result['icon']) ? _MODULE_DIR_ . $result['icon'] : null,
            'merchantUid' => $result['merchant_id'],
            'mode' => (($result['mode'] == 1) ? 'master' : 'staging'),
            'title' => ( ($title)? implode(', ', $title) : '' ),
            'reference' => Pledg::PLEDG_REFERENCE_PREFIXE . $cart->id . "_" . time(),
            'amountCents' => $total,
            'lang' => str_replace("-", "_", $this->context->language->locale),
            'countryCode'  => $this->context->country->iso_code,
            'showCloseButton' => false,
            'currency' =>  $currency->iso_code,
            'metadata'  => $metadata,
            'civility' => ( ($customer->id_gender == 1)? 'Mr' : 'Mme' ),
            'firstName' => $customer->firstname,
            'lastName' =>  $customer->lastname,
            'email' => $customer->email,
            'phoneNumber' => $phone,
            'birthCity' => '',
            'birthStateProvince' => '',
            'birthCountry' => '',
            'actionUrl' => $this->context->link->getModuleLink($this->module->name, 'validation', array(), true),
            'address' => [
                'street' => $address_invoice->address1,
                'city' => $address_invoice->city,
                'zipcode' => $address_invoice->postcode,
                'stateProvince' => '',
                'country' => $country_iso_code_invoice
            ],
            'shippingAddress' => [
                'street' => $address->address1,
                'city' => $address->city,
                'zipcode' => $address->postcode,
                'stateProvince' => '',
                'country' => $country_iso_code
            ],
        );
        
        if($customer->birthday != '0000-00-00'){
            $paramsPledg['birthDate'] = $customer->birthday;
        }

        $paramsPledg['notificationUrl'] =
            $this->context->link->getModuleLink(
                $this->module->name,
                'notification',
                array(
                    'pledgPayment' => $result['id'],
                    'amount' => $total,
                    'currency' => $currency->iso_code,
                ),
                true
            );

        if (isset($result['secret']) && !empty($result['secret'])) {
            $paramsPledg['signature'] = \Firebase\JWT\JWT::encode(["data"=>$paramsPledg], $result['secret']);
        }
        else{
            $paramsPledg['metadata'] = json_encode($paramsPledg['metadata']);
            $paramsPledg['address'] = json_encode($paramsPledg['address']);
            $paramsPledg['shippingAddress'] = json_encode($paramsPledg['shippingAddress']);
        }
        $this->context->smarty->assign([
            'paramsPledg' => $paramsPledg,
        ]);

        $this->setTemplate('module:pledg/views/templates/front/iframe.tpl');
        
    }
}
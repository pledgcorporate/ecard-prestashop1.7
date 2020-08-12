<?php

class PledgCancelModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();        
        $cart = $this->context->cart;
        Tools::redirect('index.php?controller=order');
    }
}
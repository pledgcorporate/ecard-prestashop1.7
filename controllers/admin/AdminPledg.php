<?php



require_once _PS_MODULE_DIR_ . '/pledg/class/Pledgpaiements.php';

class AdminPledgController extends ModuleAdminController

{

	public function __construct()

    {

        $this->bootstrap = true; //Gestion de l'affichage en mode bootstrap 

        $this->table = Pledgpaiements::$definition['table']; //Table de l'objet

        $this->identifier = Pledgpaiements::$definition['primary']; //Clé primaire de l'objet

        $this->className = Pledgpaiements::class; //Classe de l'objet

        $this->lang = true; //Flag pour dire si utilisation de langues ou non

 

        //Appel de la fonction parente pour pouvoir utiliser la traduction ensuite

        parent::__construct();

 
        $this->_defaultOrderBy = 'position';

        //Liste des champs de l'objet à afficher dans la liste

        $this->fields_list = [

            'position' => [

                'title' => $this->module->l('Position'),

                'align' => 'center',

                'filter_key' => 'position',

                'position' => 'position',

                'class' => 'fixed-width-md'

            ],

            'id_payment' => [

                'title' => $this->module->l('Paiement'),

                'align' => 'left'

            ],

        	'status' => [

                'title' => $this->module->l('Afficher'),

                'align' => 'left',

                'callback' => 'getStatus',

            ],

            'title' => [

                'title' => $this->module->l('Titre'),

                'lang' => true, //Flag pour dire d'utiliser la langue

                'align' => 'left',

            ],

            'merchant_id' => [

                'title' => $this->module->l('Merchant ID'),

                'align' => 'left',

            ]

        ];

 

        //Ajout d'actions sur chaque ligne

        $this->addRowAction('edit');

        $this->addRowAction('delete');

    }

    public function ajaxProcessUpdatePositions()
    {
        $way = (int)Tools::getValue('way');
        $id_quicklinks = (int)Tools::getValue('id');
        $positions = Tools::getValue('quicklinks');

        if (is_array($positions))
            foreach ($positions as $position => $value)
            {
                $pos = explode('_', $value);

                if (isset($pos[2]) && (int)$pos[2] === $id_velcroquicklinks)
                {
                        if (isset($position) && $this->updatePosition($way, $position, $id_quicklinks))
                            echo 'ok position '.(int)$position.' for id '.(int)$pos[1].'\r\n';
                        else
                            echo '{"hasError" : true, "errors" : "Can not update id '.(int)$id_quicklinks.' to position '.(int)$position.' "}';
                   
                    break;
                }
            }

    }

    public function updatePosition($way, $position, $id)
    {
        
        if (!$res = Db::getInstance()->executeS('
            SELECT `id`, `position`
            FROM `'._DB_PREFIX_.'pledg_paiements`
            ORDER BY `position` ASC'
        ))
            return false;

        foreach ($res as $quicklinks)
            if ((int)$quicklinks['id'] == (int)$id)
                $moved_quicklinks = $quicklinks;

        if (!isset($moved_quicklinks) || !isset($position))
            return false;
        var_dump($moved_quicklinks['position']);
        // < and > statements rather than BETWEEN operator
        // since BETWEEN is treated differently according to databases
        return (Db::getInstance()->execute('
            UPDATE `'._DB_PREFIX_.'pledg_paiements`
            SET `position`= `position` '.($way ? '- 1' : '+ 1').'
            WHERE `position`
            '.($way
                ? '> '.(int)$moved_quicklinks['position'].' AND `position` <= '.(int)$position
                : '< '.(int)$moved_quicklinks['position'].' AND `position` >= '.(int)$position.'
            '))
        && Db::getInstance()->execute('
            UPDATE `'._DB_PREFIX_.'pledg_paiements`
            SET `position` = '.(int)$position.'
            WHERE `id` = '.(int)$moved_quicklinks['id']));
    }

    public function getStatus($value){



    	$html = 'Non';



    	if($value == 1)

    		$html = 'Oui';



    	return $html;

    }



    public function getMode($value){



    	$html = 'Dev';



    	if($value == 1)

    		$html = 'Prod';



    	return $html;

    }



    /**

     * Gestion de la toolbar

     */

    public function initPageHeaderToolbar()

    {

 

        //Bouton d'ajout

        $this->page_header_toolbar_btn['new'] = array(

            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,

            'desc' => $this->module->l('Ajouter un paiement'),

            'icon' => 'process-icon-new'

        );

 

        parent::initPageHeaderToolbar();

    }



    /**

     * Affichage du formulaire d'ajout / création de l'objet

     * @return string

     * @throws SmartyException

     */

    public function renderForm()

    {

        //Définition du formulaire d'édition

        $this->fields_form = [

            //Entête

            'legend' => [

                'title' => $this->module->l('Mode de paiement'),

                'icon' => 'icon-cog'

            ],

            //Champs

            'input' => [

            	[

                    'type' => 'text',

                    'label' => $this->module->l('Titre'),

                    'name' => 'title',

                    'lang' => true,

                    'required' => true,

                    'empty_message' => $this->module->l('Le titre est obligatoire'),

                ],

                [

                    'type' => 'text',

                    'label' => $this->module->l('Position'),

                    'name' => 'position',

                    'required' => true,

                ],

                [

                    'type' => 'text',

                    'label' => $this->module->l('ID du paiement'),

                    'name' => 'id_payment',

                    'required' => true,

                    'empty_message' => $this->module->l('L\'ID du paiement est obligatoire'),

                ],

                [

                    'type' => 'switch', //Type de champ

                    'label' => $this->module->l('Activer le paiement'), //Label

                    'name' => 'status', //Nom

                    'values' => [

                        [

                        	'id' => 'prod',

                            'value' => 1,

                            'label' => $this->l('Production')

                        ],



                        [

                        	'id' => 'dev',

                            'value' => 0,

                            'label' => $this->l('Text')

                        ],



                    ]

                ],

                [

                    'type' => 'text',

                    'label' => $this->module->l('Merchant ID'),

                    'name' => 'merchant_id',

                    'required' => true,

                    'empty_message' => $this->module->l('Le merchant ID est obligatoire'),

                ],

                [

                    'type' => 'textarea',

                    'label' => $this->module->l('Description'),

                    'name' => 'description',

                    'lang' => true,

                    'autoload_rte' => true, //Flag pour éditeur Wysiwyg

                ],

            ],

            //Boutton de soumission

            'submit' => [

                'title' => $this->l('Save'), //On garde volontairement la traduction de l'admin par défaut

            ]

        ];

        return parent::renderForm();

    }



}
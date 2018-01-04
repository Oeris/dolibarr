<?php
/**
 * Copyright (C) 2016      Neil Orley           <neil.orley@oeris.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/dolinrt/oeris_productorservice.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once '../master.inc.php';
require_once NUSOAP_PATH.'/nusoap.php';        // Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

//require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
//require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");
//require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';



dol_syslog("Call Dolibarr webservices interfaces");

$langs->load("main");

// Enable and test if module web services is enabled
if (empty($conf->global->MAIN_MODULE_WEBSERVICES))
{
    $langs->load("admin");
    dol_syslog("Call Dolibarr webservices interfaces with module webservices disabled");
    print $langs->trans("WarningModuleNotActive",'WebServices').'.<br><br>';
    print $langs->trans("ToActivateModule");
    exit;
}

// Create the soap Object
$server = new nusoap_server();
$server->soap_defencoding='UTF-8';
$server->decode_utf8=false;
$ns='http://www.dolibarr.org/ns/';
$server->configureWSDL('WebServicesOerisSearchProductOrService',$ns);
$server->wsdl->schemaTargetNamespace=$ns;


// Define WSDL Authentication object
$server->wsdl->addComplexType(
    'authentication',
    'complexType',
    'struct',
    'all',
    '',
    array(
      'dolibarrkey' => array('name'=>'dolibarrkey','type'=>'xsd:string'),
      'sourceapplication' => array('name'=>'sourceapplication','type'=>'xsd:string'),
      'login' => array('name'=>'login','type'=>'xsd:string'),
      'password' => array('name'=>'password','type'=>'xsd:string'),
      'entity' => array('name'=>'entity','type'=>'xsd:string')
    )
);
// Define WSDL Return object
$server->wsdl->addComplexType(
    'result',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'result_code' => array('name'=>'result_code','type'=>'xsd:string'),
        'result_label' => array('name'=>'result_label','type'=>'xsd:string')
    )
);

$productorservice_fields = array(
    'id' => array('name'=>'id','type'=>'xsd:string'),
    'ref' => array('name'=>'ref','type'=>'xsd:string'),
    'label' => array('name'=>'label','type'=>'xsd:string'),
    'price_net' => array('name'=>'price_net','type'=>'xsd:string'),
    'price' => array('name'=>'price','type'=>'xsd:string')
);


// Define other specific objects
$server->wsdl->addComplexType(
    'product',
    'complexType',
    'struct',
    'all',
    '',
    $productorservice_fields
);

$server->wsdl->addComplexType(
    'products',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref'=>'SOAP-ENC:arrayType','wsdl:arrayType'=>'tns:product[]')
    ),
    'tns:product'
);



// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc='rpc';       // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse='encoded';   // encoded/literal/literal wrapped
// Better choice is document/literal wrapped but literal wrapped not supported by nusoap.


// Register WSDL
$server->register(
    'getProductOrService',
    // Entry values
    array('authentication'=>'tns:authentication','ref'=>'xsd:string'),
    // Exit values
    array('result'=>'tns:result','products'=>'tns:products'),
    $ns,
    $ns.'#getProductOrService',
    $styledoc,
    $styleuse,
    'WS to get product or service'
);

/**
 * Get produt or service
 *
 * @param	array		$authentication		Array of authentication information
 * @param	string		$ref				Ref of object
 * @return	mixed
 */
function getProductOrService($authentication,$ref='')
{
    global $db,$conf,$langs;

    dol_syslog("Function: getProductOrService login=".$authentication['login']." ref=".$ref);

    //$langcode=($lang?$lang:(empty($conf->global->MAIN_LANG_DEFAULT)?'auto':$conf->global->MAIN_LANG_DEFAULT));
    //$langs->setDefaultLang($langcode);

    if ($authentication['entity']) $conf->entity=$authentication['entity'];

    // Init and check authentication
    $objectresp=array();
    $errorcode='';$errorlabel='';
    $error=0;
    $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
    // Check parameters
    if (! $error && !$ref)
    {
        $error++;
        $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter ref is mandatory";
    }

    if (! $error)
    {
        $fuser->getrights();

        $sql = "SELECT p.rowid, p.ref, p.label, p.price, p.price_ttc";
    		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
    		if ($ref)   $sql.= " WHERE p.tosell = 1 AND p.ref like '%". $ref."%'";


        if ($fuser->rights->produit->lire || $fuser->rights->service->lire)
        {

            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);

                $i=0;
                while ($i < $num)
                {

                    $product=$db->fetch_object($resql);

                  	$productorservice_result_fields[] = array(
      	            	'id' => $product->rowid,
      	            	'ref' => $product->ref,
      	            	'label' => $product->label,
      	            	'price_net' => $product->price,
      	            	'price' => $product->price_ttc
                  	);

                    $i++;
                }

                if(!isset($productorservice_result_fields) || count($productorservice_result_fields) == 0 )
                {
                  $error++;
                  $errorcode='INFO'; $errorlabel=' Nothing found in DB with ref='.$ref.'.';
                }
                else
                {
                    // Create
                    $objectresp = array(
          			    	'result'=>array('result_code'=>'OK', 'result_label'=> ''),
          			      'products'=>$productorservice_result_fields
                    );
                }
            }
            else
            {
                $error++;
                $errorcode='NOT_FOUND'; $errorlabel='Object not found for ref='.$ref;
            }
        }
        else
        {
            $error++;
            $errorcode='PERMISSION_DENIED'; $errorlabel='User does not have permission for this request';
        }
    }

    if ($error)
    {
        $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
    }
	//var_dump($objectresp);exit;
    return $objectresp;
}



// Return the results.
$server->service(file_get_contents("php://input"));

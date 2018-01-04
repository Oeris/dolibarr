<?php
/**
 * Copyright (C) 2006-2010	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012		JF FERRY			<jfefe@aternatik.fr>
 * Copyright (C) 2012		Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2017 Neil ORLEY  <neil.orley@oeris.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/dolinrt/oeris_order.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');
require_once '../master.inc.php';
require_once NUSOAP_PATH.'/nusoap.php';        // Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

if(!empty($conf->stock->enabled)) {
  dol_include_once('/dolinrt/class/Oerisproductstockentrepot.class.php');
  require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
}

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
$server->configureWSDL('WebServicesOerisOrder',$ns);
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
				'result_label' => array('name'=>'result_label','type'=>'xsd:string'),
		)
);
$line_fields = array(
	'id' => array('name'=>'id','type'=>'xsd:string'),
	'type' => array('name'=>'type','type'=>'xsd:int'),
	'fk_commande' => array('name'=>'fk_commande','type'=>'xsd:int'),
	'fk_parent_line' => array('name'=>'fk_parent_line','type'=>'xsd:int'),
	'desc' => array('name'=>'desc','type'=>'xsd:string'),
	'qty' => array('name'=>'qty','type'=>'xsd:double'),
	'total_qty' => array('name'=>'total_qty','type'=>'xsd:double'),
	'price' => array('name'=>'price','type'=>'xsd:double'),
	'unitprice' => array('name'=>'unitprice','type'=>'xsd:double'),
	'vat_rate' => array('name'=>'vat_rate','type'=>'xsd:double'),
	'remise' => array('name'=>'remise','type'=>'xsd:double'),
	'remise_percent' => array('name'=>'remise_percent','type'=>'xsd:double'),
	'total_net' => array('name'=>'total_net','type'=>'xsd:double'),
	'total_vat' => array('name'=>'total_vat','type'=>'xsd:double'),
	'total' => array('name'=>'total','type'=>'xsd:double'),
	'date_start' => array('name'=>'date_start','type'=>'xsd:date'),
	'date_end' => array('name'=>'date_end','type'=>'xsd:date'),
	// From product
	'product_id' => array('name'=>'product_id','type'=>'xsd:int'),
	'product_ref' => array('name'=>'product_ref','type'=>'xsd:string'),
	'product_label' => array('name'=>'product_label','type'=>'xsd:string'),
	'product_desc' => array('name'=>'product_desc','type'=>'xsd:string')
);
//Retreive all extrafield for thirdsparty
// fetch optionals attributes and labels
$extrafields=new ExtraFields($db);
$extralabels=$extrafields->fetch_name_optionals_label('commandedet',true);
if (count($extrafields)>0) {
	$extrafield_line_array = array();
}
foreach($extrafields->attribute_label as $key=>$label)
{
	//$value=$object->array_options["options_".$key];
	$type =$extrafields->attribute_type[$key];
	if ($type=='date' || $type=='datetime') {$type='xsd:dateTime';}
	else {$type='xsd:string';}
	$extrafield_line_array['options_'.$key]=array('name'=>'options_'.$key,'type'=>$type);
}
$line_fields=array_merge($line_fields,$extrafield_line_array);
// Define other specific objects
$server->wsdl->addComplexType(
		'line',
		'complexType',
		'struct',
		'all',
		'',
		$line_fields
);
/*$server->wsdl->addComplexType(
		'LinesArray',
		'complexType',
		'array',
		'',
		'SOAP-ENC:Array',
		array(),
		array(
				array(
						'ref'=>'SOAP-ENC:arrayType',
						'wsdl:arrayType'=>'tns:line[]'
				)
		),
		'tns:line'
);*/
/*
$server->wsdl->addComplexType(
		'LinesArray2',
		'complexType',
		'array',
		'sequence',
		'',
		array(
				'line' => array(
						'name' => 'line',
						'type' => 'tns:line',
						'minOccurs' => '0',
						'maxOccurs' => 'unbounded'
				)
		)
);*/
$server->wsdl->addComplexType(
    'LinesArray2',
    'complexType',
    'array',
    'sequence',
    '',
    array(
        'line' => array(
            'name' => 'line',
            'type' => 'tns:line',
            'minOccurs' => '0',
            'maxOccurs' => 'unbounded'
        )
    ),
    null,
    'tns:line'
);

$order_fields = array(
	'id' => array('name'=>'id','type'=>'xsd:string'),
	'ref' => array('name'=>'ref','type'=>'xsd:string'),
	'ref_client' => array('name'=>'ref_client','type'=>'xsd:string'),
	'ref_ext' => array('name'=>'ref_ext','type'=>'xsd:string'),
	'ref_int' => array('name'=>'ref_int','type'=>'xsd:string'),
	'thirdparty_id' => array('name'=>'thirdparty_id','type'=>'xsd:int'),
	'contact_id' => array('name'=>'contact_id','type'=>'xsd:int'),
	'status' => array('name'=>'status','type'=>'xsd:int'),
	'billed' => array('name'=>'billed','type'=>'xsd:string'),
	'total_net' => array('name'=>'total_net','type'=>'xsd:double'),
	'total_vat' => array('name'=>'total_vat','type'=>'xsd:double'),
	'total_localtax1' => array('name'=>'total_localtax1','type'=>'xsd:double'),
	'total_localtax2' => array('name'=>'total_localtax2','type'=>'xsd:double'),
	'total' => array('name'=>'total','type'=>'xsd:double'),
	'date' => array('name'=>'date','type'=>'xsd:date'),
	'date_creation' => array('name'=>'date_creation','type'=>'xsd:dateTime'),
	'date_validation' => array('name'=>'date_validation','type'=>'xsd:dateTime'),
	'date_modification' => array('name'=>'date_modification','type'=>'xsd:dateTime'),
	'date_due' => array('name'=>'date_due','type'=>'xsd:dateTime'),
	'remise' => array('name'=>'remise','type'=>'xsd:string'),
	'remise_percent' => array('name'=>'remise_percent','type'=>'xsd:string'),
	'remise_absolue' => array('name'=>'remise_absolue','type'=>'xsd:string'),
	'source' => array('name'=>'source','type'=>'xsd:string'),
	'note_private' => array('name'=>'note_private','type'=>'xsd:string'),
	'note_public' => array('name'=>'note_public','type'=>'xsd:string'),
	'project_id' => array('name'=>'project_id','type'=>'xsd:string'),
	'mode_reglement_id' => array('name'=>'mode_reglement_id','type'=>'xsd:string'),
	'mode_reglement_code' => array('name'=>'mode_reglement_code','type'=>'xsd:string'),
	'mode_reglement' => array('name'=>'mode_reglement','type'=>'xsd:string'),
	'cond_reglement_id' => array('name'=>'cond_reglement_id','type'=>'xsd:string'),
	'cond_reglement_code' => array('name'=>'cond_reglement_code','type'=>'xsd:string'),
	'cond_reglement' => array('name'=>'cond_reglement','type'=>'xsd:string'),
	'cond_reglement_doc' => array('name'=>'cond_reglement_doc','type'=>'xsd:string'),
	'account_id' => array('name'=>'account_id','type'=>'xsd:int'),
	'date_livraison' => array('name'=>'date_livraison','type'=>'xsd:date'),
	'fk_delivery_address' => array('name'=>'fk_delivery_address','type'=>'xsd:int'),
	'demand_reason_id' => array('name'=>'demand_reason_id','type'=>'xsd:string'),
  	'delete_item_id' => array('name'=>'delete_item_id','type'=>'xsd:int'),
	'lines' => array('name'=>'lines','type'=>'tns:LinesArray2')
);


$get_order_fields = array(
    'cmdid' => array('name'=>'cmdid','type'=>'xsd:string'),
    'id' => array('name'=>'id','type'=>'xsd:string'),
    'ref' => array('name'=>'ref','type'=>'xsd:string'),
    'qty' => array('name'=>'qty','type'=>'xsd:string'),
    'label' => array('name'=>'label','type'=>'xsd:string'),
    'price_net' => array('name'=>'price_net','type'=>'xsd:string'),
    'tva' => array('name'=>'tva','type'=>'xsd:string'),
    'price' => array('name'=>'price','type'=>'xsd:string'),
    'total_net' => array('name'=>'total_net','type'=>'xsd:string'),
    'total_vat' => array('name'=>'total_vat','type'=>'xsd:string')
);

// Define WSDL Return object
$server->wsdl->addComplexType(
    'neworder',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id' => array('name'=>'id','type'=>'xsd:string'),
        'ref' => array('name'=>'ref','type'=>'xsd:string'),
        'ref_ext' => array('name'=>'ref_ext','type'=>'xsd:string'),
    )
);

// Define other specific objects
$server->wsdl->addComplexType(
    'getorder',
    'complexType',
    'struct',
    'all',
    '',
    $get_order_fields
);

$server->wsdl->addComplexType(
    'orders',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref'=>'SOAP-ENC:arrayType','wsdl:arrayType'=>'tns:order[]')
    ),
    'tns:getorder'
);


//Retreive all extrafield for thirdsparty
// fetch optionals attributes and labels
$extrafields=new ExtraFields($db);
$extralabels=$extrafields->fetch_name_optionals_label('commande',true);
if (count($extrafields)>0) {
	$extrafield_array = array();
}
foreach($extrafields->attribute_label as $key=>$label)
{
	//$value=$object->array_options["options_".$key];
	$type =$extrafields->attribute_type[$key];
	if ($type=='date' || $type=='datetime') {$type='xsd:dateTime';}
	else {$type='xsd:string';}
	$extrafield_array['options_'.$key]=array('name'=>'options_'.$key,'type'=>$type);
}
$order_fields=array_merge($order_fields,$extrafield_array);
$server->wsdl->addComplexType(
		'order',
		'complexType',
		'struct',
		'all',
		'',
		$order_fields
);
/*
$server->wsdl->addComplexType(
		'OrdersArray',
		'complexType',
		'array',
		'',
		'SOAP-ENC:Array',
		array(),
		array(
				array(
						'ref'=>'SOAP-ENC:arrayType',
						'wsdl:arrayType'=>'tns:order[]'
				)
		),
		'tns:order'
);*/
$server->wsdl->addComplexType(
		'OrdersArray2',
		'complexType',
		'array',
		'sequence',
		'',
		array(
				'order' => array(
						'name' => 'order',
						'type' => 'tns:order',
						'minOccurs' => '0',
						'maxOccurs' => 'unbounded'
				)
		)
);
// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc='rpc';       // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse='encoded';   // encoded/literal/literal wrapped
// Better choice is document/literal wrapped but literal wrapped not supported by nusoap.
// Register WSDL
// Register WSDL

$server->register(
		'getOrderDetail',
		array('authentication'=>'tns:authentication','id'=>'xsd:string','ref'=>'xsd:string','ref_ext'=>'xsd:string'), // Entry values
		array('result'=>'tns:result','order'=>'tns:order'),	// Exit values
		$ns,
		$ns.'#getOrderDetail',
		$styledoc,
		$styleuse,
		'WS to get a particular invoice'
);

$server->register(
    'getOrders',
    // Entry values
    array('authentication'=>'tns:authentication','ref'=>'xsd:string'),
    // Exit values
    array('result'=>'tns:result','orders'=>'tns:orders'),
    $ns,
    $ns.'#getOrders',
    $styledoc,
    $styleuse,
    'WS to get orders details'
);
$server->register(
		'getOrdersForThirdParty',
		array('authentication'=>'tns:authentication','idthirdparty'=>'xsd:string'),	// Entry values
		array('result'=>'tns:result','orders'=>'tns:OrdersArray2'),	// Exit values
		$ns,
		$ns.'#getOrdersForThirdParty',
		$styledoc,
		$styleuse,
		'WS to get all orders of a third party'
);
$server->register(
		'createOrder',
		array('authentication'=>'tns:authentication','order'=>'tns:order'),	// Entry values
		array('result'=>'tns:result','order'=>'tns:neworder'),	// Exit values
		$ns,
		$ns.'#createOrder',
		$styledoc,
		$styleuse,
		'WS to create an order'
);
$server->register(
		'updateOrder',
		array('authentication'=>'tns:authentication','order'=>'tns:order'),	// Entry values
		array('result'=>'tns:result','order'=>'tns:neworder'),	// Exit values
		$ns,
		$ns.'#updateOrder',
		$styledoc,
		$styleuse,
		'WS to update an order'
);
$server->register(
		'validOrder',
		array('authentication'=>'tns:authentication','id'=>'xsd:string'),	// Entry values
		array('result'=>'tns:result'),	// Exit values
		$ns,
		$ns.'#validOrder',
		$styledoc,
		$styleuse,
		'WS to valid an order'
);



/**
 * Get order from id, ref or ref_ext.
 *
 * @param	array		$authentication		Array of authentication information
 * @param	int			$id					Id
 * @param	string		$ref				Ref
 * @param	string		$ref_ext			Ref_ext
 * @return	array							Array result
 */
function getOrderDetail($authentication,$id='',$ref='',$ref_ext='')
{
	global $db,$conf,$langs;

	dol_syslog("Function: getOrder login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

	if ($authentication['entity']) $conf->entity=$authentication['entity'];

	// Init and check authentication
	$objectresp=array();
	$errorcode='';$errorlabel='';
	$error=0;

	$fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

	if ($fuser->societe_id) $socid=$fuser->societe_id;

	// Check parameters
	if (! $error && (($id && $ref) || ($id && $ref_ext) || ($ref && $ref_ext)))
	{
		$error++;
		$errorcode='BAD_PARAMETERS'; $errorlabel="Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
	}

	if (! $error)
	{
		$fuser->getrights();

		if ($fuser->rights->commande->lire)
		{
			$order=new Commande($db);
			$result=$order->fetch($id,$ref,$ref_ext);
			if ($result > 0)
			{
				// Security for external user
				if( $socid && ( $socid != $order->socid) )
				{
					$error++;
					$errorcode='PERMISSION_DENIED'; $errorlabel=$order->socid.'User does not have permission for this request';
				}

				if(!$error)
				{

					$linesresp=array();
					$i=0;
					foreach($order->lines as $line)
					{
						//var_dump($line); exit;
						$linesresp[]=array(
						'id'=>$line->rowid,
						'fk_commande'=>$line->fk_commande,
						'fk_parent_line'=>$line->fk_parent_line,
						'desc'=>$line->desc,
						'qty'=>$line->qty,
						'price'=>$line->price,
						'unitprice'=>$line->subprice,
						'vat_rate'=>$line->tva_tx,
						'remise'=>$line->remise,
						'remise_percent'=>$line->remise_percent,
						'product_id'=>$line->fk_product,
						'product_type'=>$line->product_type,
						'total_net'=>$line->total_ht,
						'total_vat'=>$line->total_tva,
						'total'=>$line->total_ttc,
						'date_start'=>$line->date_start,
						'date_end'=>$line->date_end,
						'product_ref'=>$line->product_ref,
						'product_label'=>$line->product_label,
						'product_desc'=>$line->product_desc
						);
						$i++;
					}

					// Create order
					$objectresp = array(
					'result'=>array('result_code'=>'OK', 'result_label'=>''),
					'order'=>array(
					'id' => $order->id,
					'ref' => $order->ref,
					'ref_client' => $order->ref_client,
					'ref_ext' => $order->ref_ext,
					'ref_int' => $order->ref_int,
					'thirdparty_id' => $order->socid,
					'status' => $order->statut,

					'total_net' => $order->total_ht,
					'total_vat' => $order->total_tva,
					'total_localtax1' => $order->total_localtax1,
					'total_localtax2' => $order->total_localtax2,
					'total' => $order->total_ttc,
					'project_id' => $order->fk_project,

					'date' => $order->date_commande?dol_print_date($order->date_commande,'dayrfc'):'',
					'date_creation' => $invoice->date_creation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
					'date_validation' => $invoice->date_validation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
					'date_modification' => $invoice->datem?dol_print_date($invoice->datem,'dayhourrfc'):'',

					'remise' => $order->remise,
					'remise_percent' => $order->remise_percent,
					'remise_absolue' => $order->remise_absolue,

					'source' => $order->source,
					'billed' => $order->billed,
					'note_private' => $order->note_private,
					'note_public' => $order->note_public,
					'cond_reglement_id' => $order->cond_reglement_id,
					'cond_reglement_code' => $order->cond_reglement_code,
					'cond_reglement' => $order->cond_reglement,
					'mode_reglement_id' => $order->mode_reglement_id,
					'mode_reglement_code' => $order->mode_reglement_code,
					'mode_reglement' => $order->mode_reglement,

					'date_livraison' => $order->date_livraison,
					'fk_delivery_address' => $order->fk_delivery_address,

					'demand_reason_id' => $order->demand_reason_id,
					'demand_reason_code' => $order->demand_reason_code
					));

					if( count($linesresp) ) {
						 $objectresp['order']['lines'] = $linesresp;
					}
				}
			}
			else
			{
				$error++;
				$errorcode='NOT_FOUND'; $errorlabel='Object not found for id='.$id.' nor ref='.$ref.' nor ref_ext='.$ref_ext;
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

	return $objectresp;
}


/**
 * Get produt or service
 *
 * @param	array		$authentication		Array of authentication information
 * @param	string		$ref				Ref of object
 * @return	mixed
 */
function getOrders($authentication,$ref='')
{
    global $db,$conf,$langs;

    dol_syslog("Function: getOrders login=".$authentication['login']." ref=".$ref);

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

		//$order = new Commande($db);
		//$result = $order->fetch($id, $ref, $ref_ext);

        $sql = "SELECT c.rowid as cmdid, c.ref, s.nom, cd.rowid, p.ref, cd.qty, p.label, p.price, cd.tva_tx, p.price_ttc, cd.total_ht, cd.total_tva";
    	$sql.= " FROM ".MAIN_DB_PREFIX."commande as c, ".MAIN_DB_PREFIX."commandedet as cd, ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."product as p";
    	if ($ref)   $sql.= " where p.rowid = cd.fk_product AND c.rowid = cd.fk_commande AND s.rowid = c.fk_soc AND c.date_cloture is NULL and c.ref = '". $ref."'";

        if ($fuser->rights->produit->lire || $fuser->rights->service->lire)
        {

            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);

                $i=0;
                while ($i < $num)
                {

                    $order=$db->fetch_object($resql);

                  	$order_result_fields[] = array(
                      	'cmdid' => $order->cmdid,
      	            	'id' => $order->rowid,
      	            	'ref' => $order->ref,
                      	'qty' => $order->qty,
      	            	'label' => $order->label,
      	            	'price_net' => $order->price,
                      	'tva' => $order->tva_tx,
      	            	'price' => $order->price_ttc,
                      	'total_net' => $order->total_ht,
                      	'total_vat' => $order->total_tva
                  	);

                    $i++;
                }

                if(!isset($order_result_fields) || count($order_result_fields) == 0 )
                {
                  $error++;
                  $errorcode='INFO'; $errorlabel=' Nothing found in DB with ref='.$ref.'.';
                }
                else
                {
                    // Create
                    $objectresp = array(
          			    	'result'=>array('result_code'=>'OK', 'result_label'=> ''),
          			      'orders'=>$order_result_fields
                    );
                }
            }
            else
            {
                $error++;
                $errorcode='NOT_FOUND'; $errorlabel='Object not found for ref='.$ref;
            }


            $sql = "SELECT c.rowid as cmdid, cd.description as ref, s.nom, cd.rowid,cd.qty, cd.subprice as price, cd.tva_tx, (cd.subprice+(cd.subprice*cd.tva_tx/100)) as price_ttc ";
            $sql .= " FROM ".MAIN_DB_PREFIX."commande as c, ".MAIN_DB_PREFIX."commandedet as cd, ".MAIN_DB_PREFIX."societe as s ";
            if ($ref)   $sql.= " where cd.fk_product is NULL AND c.rowid = cd.fk_commande AND s.rowid = c.fk_soc AND c.date_cloture is NULL and c.ref = '". $ref."' ";


            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);

                $i=0;
                while ($i < $num)
                {

                    $order=$db->fetch_object($resql);

                  	$order_result_fields[] = array(
                      'cmdid' => $order->cmdid,
      	            	'id' => $order->rowid,
      	            	'ref' => $order->ref,
                      'qty' => $order->qty,
      	            	'label' => $order->label,
      	            	'price_net' => $order->price,
                      'tva' => $order->tva_tx,
      	            	'price' => $order->price_ttc
                  	);

                    $i++;
                }

                if(!isset($order_result_fields) || count($order_result_fields) == 0 )
                {
                  $error++;
                  $errorcode='INFO'; $errorlabel=' Nothing found in DB with ref='.$ref.'.';
                }
                else
                {
                    // Create
                    $objectresp = array(
          			    	'result'=>array('result_code'=>'OK', 'result_label'=> ''),
          			      'orders'=>$order_result_fields
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
/**
 * Get list of orders for third party
 *
 * @param	array		$authentication		Array of authentication information
 * @param	int			$idthirdparty		Id of thirdparty
 * @return	array							Array result
 */
function getOrdersForThirdParty($authentication,$idthirdparty)
{
	global $db,$conf,$langs;
	dol_syslog("Function: getOrdersForThirdParty login=".$authentication['login']." idthirdparty=".$idthirdparty);
	if ($authentication['entity']) $conf->entity=$authentication['entity'];
	// Init and check authentication
	$objectresp=array();
	$errorcode='';$errorlabel='';
	$error=0;
	$fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
	if ($fuser->societe_id) $socid=$fuser->societe_id;
	// Check parameters
	if (! $error && empty($idthirdparty))
	{
		$error++;
		$errorcode='BAD_PARAMETERS'; $errorlabel='Parameter id is not provided';
	}
	if (! $error)
	{
		$linesorders=array();
		$sql.='SELECT c.rowid as orderid';
		$sql.=' FROM '.MAIN_DB_PREFIX.'commande as c';
		$sql.=" WHERE c.entity = ".$conf->entity;
		if ($idthirdparty != 'all' ) $sql.=" AND c.fk_soc = ".$db->escape($idthirdparty);
		$resql=$db->query($sql);
		if ($resql)
		{
			$num=$db->num_rows($resql);
			$i=0;
			while ($i < $num)
			{
				// En attendant remplissage par boucle
				$obj=$db->fetch_object($resql);
				$order=new Commande($db);
				$order->fetch($obj->orderid);
				// S�curit� pour utilisateur externe
				if( $socid && ( $socid != $order->socid) )
				{
					$error++;
					$errorcode='PERMISSION_DENIED'; $errorlabel=$order->socid.' User does not have permission for this request';
				}
				if(!$error)
				{
					// Define lines of invoice
					$linesresp=array();
					foreach($order->lines as $line)
					{
						$linesresp[]=array(
						'id'=>$line->rowid,
						'type'=>$line->product_type,
						'fk_commande'=>$line->fk_commande,
						'fk_parent_line'=>$line->fk_parent_line,
						'desc'=>$line->desc,
						'qty'=>$line->qty,
						'price'=>$line->price,
						'unitprice'=>$line->subprice,
						'tva_tx'=>$line->tva_tx,
						'remise'=>$line->remise,
						'remise_percent'=>$line->remise_percent,
						'total_net'=>$line->total_ht,
						'total_vat'=>$line->total_tva,
						'total'=>$line->total_ttc,
						'date_start'=>$line->date_start,
						'date_end'=>$line->date_end,
						'product_id'=>$line->fk_product,
						'product_ref'=>$line->product_ref,
						'product_label'=>$line->product_label,
						'product_desc'=>$line->product_desc
						);
					}
					// Now define invoice
					$linesorders[]=array(
					'id' => $order->id,
					'ref' => $order->ref,
					'ref_client' => $order->ref_client,
					'ref_ext' => $order->ref_ext,
					'ref_int' => $order->ref_int,
					'socid' => $order->socid,
					'status' => $order->statut,
					'total_net' => $order->total_ht,
					'total_vat' => $order->total_tva,
					'total_localtax1' => $order->total_localtax1,
					'total_localtax2' => $order->total_localtax2,
					'total' => $order->total_ttc,
					'project_id' => $order->fk_project,
					'date' => $order->date_commande?dol_print_date($order->date_commande,'dayrfc'):'',
					'remise' => $order->remise,
					'remise_percent' => $order->remise_percent,
					'remise_absolue' => $order->remise_absolue,
					'source' => $order->source,
					'billed' => $order->billed,
					'note_private' => $order->note_private,
					'note_public' => $order->note_public,
					'cond_reglement_id' => $order->cond_reglement_id,
					'cond_reglement' => $order->cond_reglement,
					'cond_reglement_doc' => $order->cond_reglement_doc,
					'cond_reglement_code' => $order->cond_reglement_code,
					'mode_reglement_id' => $order->mode_reglement_id,
					'mode_reglement' => $order->mode_reglement,
					'mode_reglement_code' => $order->mode_reglement_code,
					'date_livraison' => $order->date_livraison,
					'demand_reason_id' => $order->demand_reason_id,
					'demand_reason_code' => $order->demand_reason_code,
					'lines' => $linesresp
					);
				}
				$i++;
			}
			$objectresp=array(
			'result'=>array('result_code'=>'OK', 'result_label'=>''),
			'orders'=>$linesorders
			);
		}
		else
		{
			$error++;
			$errorcode=$db->lasterrno(); $errorlabel=$db->lasterror();
		}
	}
	if ($error)
	{
		$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}
	return $objectresp;
}
/**
 * Create order
 *
 * @param	array		$authentication		Array of authentication information
 * @param	array		$order				Order info
 * @return	int								Id of new order
 */
function createOrder($authentication,$order)
{
	global $db,$conf,$langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
	$now=dol_now();
	dol_syslog("Function: createOrder login=".$authentication['login']." socid :".$order['socid']);
	if ($authentication['entity']) $conf->entity=$authentication['entity'];
	// Init and check authentication
	$objectresp=array();
	$errorcode='';$errorlabel='';
	$error=0;
	$fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
	// Check parameters
	if (! $error)
	{
		$newobject=new Commande($db);
		$newobject->socid=$order['thirdparty_id'];
		$newobject->type=$order['type'];
		$newobject->ref_ext=$order['ref_ext'];
		$newobject->ref_client=$order['ref_client'];
		$newobject->date=$now;
		$newobject->date_lim_reglement=dol_stringtotime($order['date_due'],'dayrfc');
		$newobject->date_livraison=dol_stringtotime($order['date_due'],'dayrfc');
		$newobject->note_private=$order['note_private'];
		$newobject->note_public=$order['note_public'];
		$newobject->statut=Commande::STATUS_DRAFT;	// We start with status draft
		$newobject->billed=$order['billed'];
		$newobject->fk_project=$order['project_id'];
		$newobject->fk_delivery_address=$order['fk_delivery_address'];
		$newobject->cond_reglement_id=$order['cond_reglement_id'];
		$newobject->mode_reglement_id=$order['mode_reglement_id'];
		$newobject->demand_reason_id=$order['demand_reason_id'];
		$newobject->date_creation=$now;



		// ---- Get the 1st account if empty
		if (empty($order['account_id']) || $order['account_id'] == - 1) {
			$order['account_id'] = 1;
		}
		$account = new Account($db);
		$account->fetch($order['account_id']);

		// take mode_reglement and cond_reglement from thirdparty
		$soc = new Societe($db);
		$res = $soc->fetch($newobject->socid);
		if ($res > 0) {
			$newobject->mode_reglement_id = ! empty($order['mode_reglement_id']) ? $order['mode_reglement_id'] : $soc->mode_reglement_id;
			if (! empty($soc->fk_account)) {
				$newobject->fk_account = ! empty($order['account_id']) ? $order['account_id'] : $soc->fk_account;
			} else
				if (! empty($account->id)) {
					$newobject->fk_account = ! empty($order['account_id']) ? $order['account_id'] : $account->id;
				}
			$newobject->cond_reglement_id = ! empty($order['cond_reglement_id']) ? $order['cond_reglement_id'] : $soc->cond_reglement_id;
		} else {
			$newobject->mode_reglement_id = $order['mode_reglement_id'];
			$newobject->cond_reglement_id = $order['cond_reglement_id'];
			if (! empty($account->id)) {
				$newobject->fk_account = ! empty($order['account_id']) ? $order['account_id'] : $account->id;
			}
		}

		// Search idwharehouse
	/*	if (! empty($conf->stock->enabled)) {
			$entrepot_id = new OerisProductStockEntrepot($db);
			$mouvement = new MouvementStock($db);
			$fk_entrepot = 0;
		}*/




		// Retrieve all extrafield for order
		// fetch optionals attributes and labels
		$extrafields=new ExtraFields($db);
		$extralabels=$extrafields->fetch_name_optionals_label('commandet',true);
		foreach($extrafields->attribute_label as $key=>$label)
		{
			$key='options_'.$key;
			$newobject->array_options[$key]=$order[$key];
		}
		// Trick because nusoap does not store data with same structure if there is one or several lines
		/*$arrayoflines=array();
		if (isset($order['lines']['line'][0])) $arrayoflines=$order['lines']['line'];
		else $arrayoflines=$order['lines'];
		*/
		$arrayoflines = array();
		if (isset($order['lines']['line'][0]))
			$arrayoflines = $invoice['lines']['line'];
		else
			if (isset($order['lines']))
				$arrayoflines = $order['lines'];


		foreach($arrayoflines as $key => $line)
		{
			// $key can be 'line' or '0','1',...
			$newline=new OrderLine($db);
			$newline->type=$line['type'];
			$newline->desc=$line['desc'];
			$newline->fk_product=$line['product_id'];
			$newline->tva_tx=$line['vat_rate'];
			$newline->qty=$line['qty'];
			$newline->price=$line['price'];
			$newline->subprice=$line['unitprice'];
			$newline->total_ht=$line['total_net'];
			$newline->total_tva=$line['total_vat'];
			$newline->total_ttc=$line['total'];
			$newline->date_start=$line['date_start'];
			$newline->date_end=$line['date_end'];
			// Retrieve all extrafield for lines
			// fetch optionals attributes and labels
			$extrafields=new ExtraFields($db);
			$extralabels=$extrafields->fetch_name_optionals_label('commandedet',true);
			foreach($extrafields->attribute_label as $key=>$label)
			{
				$key='options_'.$key;
				$newline->array_options[$key]=$line[$key];
			}
/*
			if (! empty($conf->stock->enabled) && empty($fk_entrepot)) {
				if ($entrepot_id->getFirstAvailableWharehouse($line['product_id']) > 0) {
					$fk_entrepot = $entrepot_id->fk_entrepot;
					$mouvement->livraison($fuser, $line['product_id'], $fk_entrepot, $line['qty'], $line['unitprice']);
				}
			}*/

			$newobject->lines[]=$newline;
		}
		$db->begin();
		dol_syslog("Webservice server_order:: order creation start", LOG_DEBUG);
		$result=$newobject->create($fuser);
		dol_syslog('Webservice server_order:: order creation done with $result='.$result, LOG_DEBUG);
		if ($result < 0)
		{
			dol_syslog("Webservice server_order:: order creation failed", LOG_ERR);
			$error++;
		}

		if (! $error) {
			// ---- Ajoute le contact de facturation
			// ---- id = Rowid, contact_source = external, contact_type=
			//$lesTypes = $new_propal->liste_type_contact($propal["contact_source"], 'position', 0, 1, 'BILLING');
			$lesTypes = $newobject->liste_type_contact('external', 'position', 0, 1, 'BILLING');
			foreach ($lesTypes as $key => $value) {
				$type = $key;
			}
			$result = $newobject->add_contact($order['contact_id'], $type, 'external');
			if ($result < 0) {
				$error ++;
				$errorcode = "INFO";
				$errorlabel = "Impossible d'ajouter un contact a cette facture";
			}
		}

		if (! $error && $order['status'] == Commande::STATUS_VALIDATED) // We want invoice to have status validated
		{
			//$result = $new_propal->validate($fuser, $fk_entrepot);
			$result = $newobject->valid($fuser);
			if ($result < 0) {
				$error ++;
			} else {
				// Define output language
				$outputlangs = $langs;
				$ret = $newobject->fetch($newobject->id); // Reload to get new records
				$newobject->generateDocument($newobject->modelpdf, $outputlangs);
			}

			//$result = $new_propal->set_draft($fuser, $fk_entrepot); // passage de la facture en brouillon
			$result = $newobject->set_draft($fuser); // passage de la facture en brouillon
			if ($result < 0) {
				$error ++;
			}
		}


		if ($result >= 0)
		{
			dol_syslog("Webservice server_order:: order creation & validation succeeded, commit", LOG_DEBUG);
			$db->commit();
			$objectresp=array('result'=>array('result_code'=>'OK', 'result_label'=>''),
							  'order' => array( 'id' => $newobject->id, 'ref' => $newobject->ref, 'ref_ext' => $newobject->ref_client ));
		}
		else
		{
			dol_syslog("Webservice server_order:: order creation or validation failed, rollback", LOG_ERR);
			$db->rollback();
			$error++;
			$errorcode='KO';
			$errorlabel=$newobject->error;
		}
	}
	if ($error)
	{
		$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}
	return $objectresp;
}
/**
 * Valid an order
 *
 * @param	array		$authentication		Array of authentication information
 * @param	int			$id					Id of order to validate
 * @return	array							Array result
 */
function validOrder($authentication,$id='')
{
	global $db,$conf,$langs;
	dol_syslog("Function: validOrder login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);
	// Init and check authentication
	$objectresp=array();
	$errorcode='';$errorlabel='';
	$error=0;
	if ($authentication['entity']) $conf->entity=$authentication['entity'];
	$fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
	if (! $error)
	{
		$fuser->getrights();
		if ($fuser->rights->commande->lire)
		{
			$order=new Commande($db);
			$result=$order->fetch($id,$ref,$ref_ext);
			$order->fetch_thirdparty();
			$db->begin();
			if ($result > 0)
			{
				$result=$order->valid($fuser);
				if ($result	>= 0)
				{
					// Define output language
					$outputlangs = $langs;
          $ret = $order->fetch($order->id); // Reload to get new records
					$order->generateDocument($order->modelpdf, $outputlangs);
				}
				else
				{
					$db->rollback();
					$error++;
					$errorcode='KO';
					$errorlabel=$newobject->error;
				}
			}
			else
			{
				$db->rollback();
				$error++;
				$errorcode='KO';
				$errorlabel=$newobject->error;
			}
		}
		else
		{
			$db->rollback();
			$error++;
			$errorcode='KO';
			$errorlabel=$newobject->error;
		}
	}
	if ($error)
	{
		$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}
	else
	{
		$db->commit();
		$objectresp= array('result'=>array('result_code'=>'OK', 'result_label'=>''));
	}
	return $objectresp;
}
/**
 * Update an order
 *
 * @param	array		$authentication		Array of authentication information
 * @param	array		$order				Order info
 * @return	array							Array result
 */
function updateOrder($authentication,$order)
{
	global $db,$conf,$langs;
	$now=dol_now();
	dol_syslog("Function: updateOrder login=".$authentication['login']);
	if ($authentication['entity']) $conf->entity=$authentication['entity'];
	// Init and check authentication
	$objectresp=array();
	$errorcode='';$errorlabel='';
	$error=0;

	$fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
	// Check parameters
	if (empty($order['id']) && empty($order['ref']) && empty($order['ref_ext']) && empty($order['billed']) && empty($order['thirdparty_id']))	{
		$error++; $errorcode='KO'; $errorlabel="Order id or ref or ref_ext or billed is mandatory.";
	    $error++; $errorcode='KO'; $errorlabel=$result.' '.$object->error;
	    $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
	    return $objectresp;
	}
	if (! $error)
	{


	$object=new Commande($db);
    $result=$object->fetch($order['id'],$order['ref'],$order['ref_ext']);

    // --- protége de la modification d'une commande qui n'est pas DRAFT ou VALIDATED
	if($object->statut != Commande::STATUS_DRAFT && $object->statut != Commande::STATUS_VALIDATED) {
		$error ++;
		$errorcode = 'KO';
		$errorlabel = 'Order is closed and/or billed';
		$objectresp = array(
		'result' => array(
		'result_code' => $errorcode,
		'result_label' => $errorlabel
		)
		);
		return $objectresp;
	}

    if(isset($order['delete_item_id']) && !empty($order['delete_item_id']))
    {
        $objectfound=false;
    	include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';



    	if (!empty($object->id)) {
				$objectfound = true;

				$result = $object->set_draft($fuser);
				if ($result < 0) {
					$error ++;
					$errorcode = 'KO';
					$errorlabel = $result . ' ' . $object->error;
					$objectresp = array(
					'result' => array(
					'result_code' => $errorcode,
					'result_label' => $errorlabel
					)
					);
					return $objectresp;
				}

    			// ---- Supprime un objet du stock
				if (! empty($conf->stock->enabled)) {
					$objectLine = new OrderLine($db);
					$resultLine = $objectLine->fetch($order['delete_item_id']);
					if($resultLine > 0) {
						$entrepot_id = new OerisProductStockEntrepot($db);
						$mouvement = new MouvementStock($db);
						$fk_entrepot = 0;
						if ($entrepot_id->getFirstAvailableWharehouse($objectLine->fk_product) > 0) {
							$fk_entrepot = $entrepot_id->fk_entrepot;
						}
						if($fk_entrepot > 0) {
							$retmouvement = $mouvement->reception($fuser, $objectLine->fk_product, $fk_entrepot, $objectLine->qty, $objectLine->subprice);
						}
					}
					//--- Debug
				/*	$objectresp = array(
					'result' => array(
					'result_code' => 'RET',
					'result_label' => '$line["product_id"] = '.$line['product_id'].', $resultLine = '.$resultLine.', $resultLine->error = '.($resultLine->error).', $fk_entrepot = '.$fk_entrepot.', $retmouvement = '.$retmouvement
					)
					);
					return $objectresp;*/

				}

				// ---- Dolibarr 5 b�ta
				$delresult = $object->deleteline($fuser, $order['delete_item_id']);

				// ---- Avant dolibarr 5 b�ta
				// $delresult=$object->deleteline($order['delete_item_id']);



          		$result = $object->valid($fuser);
				if ($result >= 0) {
					// Define output language
					$outputlangs = $langs;
					$ret = $object->fetch($object->id); // Reload to get new records
					if ($object->generateDocument($object->modelpdf, $outputlangs) == 0) {
						$info = 'Commande mise a jour, mais impossible de g�n�rer le document';
					}
				}else {
					$error ++;
					$errorcode = 'KO';
					$errorlabel = $result . ' ' . $object->error;
					$objectresp = array(
					'result' => array(
					'result_code' => $errorcode,
					'result_label' => $errorlabel
					)
					);
					return $objectresp;
				}

				$objectresp = array(
				'result' => array(
				'result_code' => 'OK',
				'result_label' => $info
				),
				'order' => array(
				'id' => $object->id,
				'ref' => $object->ref,
				'ref_ext' => $object->ref_ext
				)
				);
				return $objectresp;

        } else {

    			$error++;
    			$errorcode='NOT_FOUND';
    			$errorlabel='Order id='.$order['id'].' ref='.$order['ref'].' ref_ext='.$order['ref_ext'].' cannot be found';
          $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
          return $objectresp;
    		}

    }
    else
    {

        $objectfound=false;
    	include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

    	if (!empty($object->id)) {
    			$objectfound=true;


          $result=$object->set_draft($fuser);
          if($result<0)
          {
            $error++; $errorcode='KO'; $errorlabel=$result.' '.$object->error;
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
            return $objectresp;
          }

          //---- Load la societe
          /*include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
          $socstatic = new Societe($db);
          $socstatic->fetch($order['thirdparty_id']);
          $object->thirdparty=$socstatic;
          */


          // Trick because nusoap does not store data with same structure if there is one or several lines
          $arrayoflines=array();
          if (isset($order['lines']['line'][0])) $arrayoflines=$order['lines']['line'];
          else if(isset($order['lines'])) $arrayoflines=$order['lines'];

          if(count($arrayoflines)>0)
          {
            foreach($arrayoflines as $key => $line)
            {

            	if (! empty($conf->stock->enabled)) {
					$objectLine = new OrderLine($db);
					$entrepot_id = new OerisProductStockEntrepot($db);
					$mouvement = new MouvementStock($db);
					$fk_entrepot = 0;
				}

                //---- Permet la mise a jour des quantit�s pour une meme r�f�rence de produit
                $currentqty = 0;
                foreach($object->lines as $currentline) {
        			if ( ($currentline->fk_product == $line['product_id'] && $line['product_id']>0) || ($currentline->product_ref == $line['product_ref'] && $line['product_ref'] != '') )
                  	{

                  		$resultLine = $objectLine->fetch($currentline->rowid);
						if ($entrepot_id->getFirstAvailableWharehouse($objectLine->fk_product) > 0) {
							$fk_entrepot = $entrepot_id->fk_entrepot;
						}
                  		/*
                  		if($currentline->desc == '') {
	                      //---- Supprime la ligne produit
	                      $result=$object->deleteline($fuser,$currentline->rowid);
	                      $currentqty += $currentline->qty;
                    	}
                    	*/
                  		// ---- Supprime la ligne produit
						if ($line['total_qty'] > 0) {
							if ($currentline->desc != '' && $currentline->desc == trim($line['desc'])) {
								$result = $object->deleteline($fuser,$currentline->rowid);
								if (! empty($conf->stock->enabled)) {
									$mouvement->reception($fuser, $currentline->fk_product, $fk_entrepot, $currentline->qty, $currentline->subprice);
								}
								$currentqty = 0;
							} else {
								$currentqty = 0;
							}
						} else {
							$result = $object->deleteline($fuser,$currentline->rowid);
							if (! empty($conf->stock->enabled)) {
								$mouvement->reception($fuser, $currentline->fk_product, $fk_entrepot, $currentline->qty, $currentline->subprice);
							}
							$currentqty += $currentline->qty;
						}
						//--- Debug
					/*	$objectresp = array(
						'result' => array(
						'result_code' => 'RET',
						'result_label' => '$line["product_id"] = '.$line['product_id'].', $resultLine = '.$resultLine.', $resultLine->error = '.($resultLine->error).', $fk_entrepot = '.$fk_entrepot.', $retmouvement = '.$retmouvement
						)
						);
						return $objectresp;*/
                    //print $currentline->desc."<br>";
                  	}


        		}
                //exit;
                //---- Si la quantit� est a 0, laisse le produit pr�sent mais sera factur� a 0�
                if($currentqty < 0) {
                  $currentqty = 0;
                }

                // $key can be 'line' or '0','1',...
                $newproduct=new Product($db);
                if($newproduct->fetch($line['product_id'],'','', '')<0)
                {
                  $error++; $errorcode='KO'; $errorlabel=$newproduct->error;
                  $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
                  return $objectresp;
                }


                $result = $object->addline($newproduct->description, $newproduct->price, $line['qty']+$currentqty, $newproduct->tva_tx, 0, 0, $newproduct->id, 0, 0, 0, 'HT', $newproduct->price_ttc, '', '', $newproduct->type, -1, 0, 0, null,0, $newproduct->label,0, null, '', 0);
                if($result<0)
                {

                  $error++; $errorcode='KO'; $errorlabel=$result.' '.$object->error;
                  $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
                  return $objectresp;
                }

            	if (! empty($conf->stock->enabled) && $entrepot_id->getFirstAvailableWharehouse($newproduct->id) > 0) {
					$fk_entrepot = $entrepot_id->fk_entrepot;
					$mouvement->livraison($fuser, $newproduct->id, $fk_entrepot, $line['qty'], $newproduct->price);
				}



            }

            // Define output language
						$outputlangs = $langs;
            $ret = $object->fetch($object->id); // Reload to get new records
						$object->generateDocument($object->modelpdf, $outputlangs);

          }

         // $db->begin();

          if (isset($order['status']))
    			{
    				if ($order['status'] == -1) $result=$object->cancel($fuser);
    				if ($order['status'] == 1)
    				{
    					$result=$object->valid($fuser);
    					if ($result	>= 0)
    					{
    						// Define output language
    						$outputlangs = $langs;
                $ret = $object->fetch($object->id); // Reload to get new records
    						$object->generateDocument($object->modelpdf, $outputlangs);

    					}
    				}
            if ($order['status'] == 0)  $result=$object->set_reopen($fuser);
    				if ($order['status'] == 3)  $result=$object->cloture($fuser);
    			}


    			if (isset($order['billed']))
    			{
    				if ($order['billed'])   $result=$object->classifyBilled($fuser);
    				if (! $order['billed']) $result=$object->classifyUnBilled($fuser);
    			}


    			//Retreive all extrafield for object
    			// fetch optionals attributes and labels
    			$extrafields=new ExtraFields($db);
    			$extralabels=$extrafields->fetch_name_optionals_label('commande',true);
    			foreach($extrafields->attribute_label as $key=>$label)
    			{
    				$key='options_'.$key;
    				if (isset($order[$key]))
    				{
    					$result=$object->setValueFrom($key, $order[$key], 'commande_extrafields');
    				}
    			}
    			if ($result <= 0) {
    				$error++;
    			}


    		}


    		if ((! $error) && ($objectfound))
    		{
    			$db->commit();
    			$objectresp=array(
    					'result'=>array('result_code'=>'OK', 'result_label'=>''),
    					'order'=>array('id'=>$object->id, 'ref'=>$object->ref, 'ref_ext'=>$object->ref_ext)
          );
    		}
    		elseif($objectfound)
    		{
    			$db->rollback();
    			$error++;
    			$errorcode='KO';
    			$errorlabel .=' Error '.$result;
    		} else {
    			$error++;
    			$errorcode='NOT_FOUND';
    			$errorlabel='Order id='.$order['id'].' ref='.$order['ref'].' ref_ext='.$order['ref_ext'].' cannot be found';
    		}
    	}
    	if ($error)
    	{
    		$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
    	}
    	return $objectresp;
  }
}
// Return the results.
$server->service(file_get_contents("php://input"));
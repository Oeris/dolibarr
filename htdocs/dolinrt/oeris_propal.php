<?php
/**
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
 *       \file       htdocs/dolinrt/oeris_propal.php
 *       \brief      File that is entry point to call Dolibarr WebServices for propal
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once '../master.inc.php';
require_once NUSOAP_PATH.'/nusoap.php';		// Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formorder.class.php';

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
$server->configureWSDL('WebServicesOerisPropal',$ns);
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


// Define other specific objects
$server->wsdl->addComplexType(
    'line',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id' => array('name'=>'id','type'=>'xsd:string'),
        'type' => array('name'=>'type','type'=>'xsd:int'),
        'desc' => array('name'=>'desc','type'=>'xsd:string'),
        'vat_rate' => array('name'=>'vat_rate','type'=>'xsd:double'),
        'qty' => array('name'=>'qty','type'=>'xsd:double'),
        'total_qty' => array('name'=>'total_qty','type'=>'xsd:double'),
        'unitprice' => array('name'=>'unitprice','type'=>'xsd:double'),
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
    )
);

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


$server->wsdl->addComplexType(
    'propal',
    'complexType',
    'struct',
    'all',
    '',
    array(
    	'id' => array('name'=>'id','type'=>'xsd:string'),
        'ref' => array('name'=>'ref','type'=>'xsd:string'),
        'ref_ext' => array('name'=>'ref_ext','type'=>'xsd:string'),
        'thirdparty_id' => array('name'=>'thirdparty_id','type'=>'xsd:int'),
        'fk_user_author' => array('name'=>'fk_user_author','type'=>'xsd:string'),
        'fk_user_valid' => array('name'=>'fk_user_valid','type'=>'xsd:string'),
        'date' => array('name'=>'date','type'=>'xsd:date'),
        'date_livraison' => array('name'=>'date_livraison','type'=>'xsd:date'),
        'date_creation' => array('name'=>'date_creation','type'=>'xsd:dateTime'),
        'date_validation' => array('name'=>'date_validation','type'=>'xsd:dateTime'),
    	'payment_mode_id' => array('name'=>'payment_mode_id','type'=>'xsd:string'),
        'cond_reglement_id' => array('name'=>'cond_reglement_id','type'=>'xsd:string'),
    	'payment_mode' => array('name'=>'payment_mode','type'=>'xsd:string'),
        'cond_reglement' => array('name'=>'cond_reglement','type'=>'xsd:string'),
        'account_id' => array('name'=>'account_id','type'=>'xsd:string'),
        'total_net' => array('name'=>'type','type'=>'xsd:double'),
        'total_vat' => array('name'=>'type','type'=>'xsd:double'),
        'total' => array('name'=>'type','type'=>'xsd:double'),
        'note_private' => array('name'=>'note_private','type'=>'xsd:string'),
        'note_public' => array('name'=>'note_public','type'=>'xsd:string'),
        'status' => array('name'=>'status','type'=>'xsd:int'),
        'delete_item_id' => array('name'=>'delete_item_id','type'=>'xsd:int'),
        'contact_id' => array('name'=>'contact_id','type'=>'xsd:int'),
        'lines' => array('name'=>'lines','type'=>'tns:LinesArray2')
    )
);

// Define WSDL Return object
$server->wsdl->addComplexType(
    'newpropal',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id' => array('name'=>'id','type'=>'xsd:string'),
        'ref' => array('name'=>'ref','type'=>'xsd:string'),
        'ref_ext' => array('name'=>'ref_ext','type'=>'xsd:string'),
        'total_ttc' => array('name'=>'total_ttc','type'=>'xsd:string'),
    )
);


$server->wsdl->addComplexType(
    'PropalsArray2',
    'complexType',
    'array',
    'sequence',
    '',
    array(
        'propal' => array(
            'name' => 'propal',
            'type' => 'tns:propal',
            'minOccurs' => '0',
            'maxOccurs' => 'unbounded'
        )
    ),
    null,
    'tns:propal'
);

	// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
	// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
	// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc = 'rpc'; // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse = 'encoded'; // encoded/literal/literal wrapped
                     // Better choice is document/literal wrapped but literal wrapped not supported by nusoap.

// Register WSDL
$server->register('getPropal',
	// Entry values
	array(
	'authentication' => 'tns:authentication',
	'id' => 'xsd:string',
	'ref' => 'xsd:string',
	'ref_ext' => 'xsd:string'
	),
	// Exit values
	array(
	'result' => 'tns:result',
	'propal' => 'tns:propal'
	), $ns, $ns . '#getPropal', $styledoc, $styleuse, 'WS to get a particular propal');

$server->register('createPropal',
	// Entry values
	array(
	'authentication' => 'tns:authentication',
	'propal' => 'tns:propal'
	),
	// Exit values
	array(
	'result' => 'tns:result',
	'propal' => 'tns:newpropal'
	), $ns, $ns . '#createPropal', $styledoc, $styleuse, 'WS to create an propal');

$server->register('updatePropal',
	// Entry values
	array(
	'authentication' => 'tns:authentication',
	'propal' => 'tns:propal'
	),
	// Exit values
	array(
	'result' => 'tns:result',
	'propal' => 'tns:newpropal'
	), $ns, $ns . '#updatePropal', $styledoc, $styleuse, 'WS to update an propal');



/**
 * Get propal from id, ref or ref_ext.
 *
 * @param array $authentication
 *        	Array of authentication information
 * @param int $id
 *        	Id
 * @param string $ref
 *        	Ref
 * @param string $ref_ext
 *        	Ref_ext
 * @return array Array result
 */
function getPropal($authentication, $id = '', $ref = '', $ref_ext = '')
{
	global $db, $conf, $langs;

	dol_syslog("Function: getInvoice login=" . $authentication['login'] . " id=" . $id . " ref=" . $ref . " ref_ext=" . $ref_ext);

	if ($authentication['entity'])
		$conf->entity = $authentication['entity'];

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);
	// Check parameters
	if (! $error && (($id && $ref) || ($id && $ref_ext) || ($ref && $ref_ext))) {
		$error ++;
		$errorcode = 'BAD_PARAMETERS';
		$errorlabel = "Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
	}

	if (! $error) {
		$fuser->getrights();

		if ($fuser->rights->propal->lire) {
			$propal = new Propal($db);
			$result = $propal->fetch($id, $ref, $ref_ext);
			if ($result > 0) {
				$linesresp = array();
				$i = 0;
				foreach ($propal->lines as $line) {
					// var_dump($line); exit;
					$linesresp[] = array(
						'id' => $line->rowid,
						'type' => $line->product_type,
						'desc' => dol_htmlcleanlastbr($line->desc),
						'total_net' => $line->total_ht,
						'total_vat' => $line->total_tva,
						'total' => $line->total_ttc,
						'vat_rate' => $line->tva_tx,
						'qty' => $line->qty,
						'unitprice' => $line->subprice,
						'date_start' => $line->date_start ? dol_print_date($line->date_start, 'dayrfc') : '',
						'date_end' => $line->date_end ? dol_print_date($line->date_end, 'dayrfc') : '',
						'product_id' => $line->fk_product,
						'product_ref' => $line->product_ref,
						'product_label' => $line->product_label,
						'product_desc' => $line->product_desc
					);
					$i ++;
				}

				// return
				$objectresp = array(
					'result' => array(
					'result_code' => 'OK',
					'result_label' => ''
				),
					'propal' => array(
						'id' => $propal->id,
						'ref' => $propal->ref,
						'ref_ext' => $propal->ref_ext ? $propal->ref_ext : '', // If not defined, field is not added into soap
						'thirdparty_id' => $propal->socid,
						'fk_user_author' => $propal->user_author ? $propal->user_author : '',
						'fk_user_valid' => $propal->user_valid ? $propal->user_valid : '',
						'date' => $propal->date ? dol_print_date($propal->date, 'dayrfc') : '',
						'date_livraison' => $propal->date_livraison ? dol_print_date($propal->date_livraison, 'dayrfc') : '',
						'date_creation' => $propal->date_creation ? dol_print_date($propal->date_creation, 'dayhourrfc') : '',
						'date_validation' => $propal->date_validation ? dol_print_date($propal->date_creation, 'dayhourrfc') : '',
						'total_net' => $propal->total_ht,
						'total_vat' => $propal->total_tva,
						'total' => $propal->total_ttc,
						'note_private' => $propal->note_private ? $propal->note_private : '',
						'note_public' => $propal->note_public ? $propal->note_public : '',
						'status' => $propal->statut,
						'cond_reglement_id' => $propal->cond_reglement_id ? $propal->cond_reglement_id : '',
						'cond_reglement' => $propal->cond_reglement ? $propal->cond_reglement : '',
						'payment_mode_id' => $propal->mode_reglement_id ? $propal->mode_reglement_id : '',
						'payment_mode' => $propal->mode_reglement ? $propal->mode_reglement : '',
						'account_id' => $propal->fk_account ? $propal->fk_account : '',
						'lines' => $linesresp
					)
				);
			} else {
				$error ++;
				$errorcode = 'NOT_FOUND';
				$errorlabel = 'Object not found for id=' . $id . ' nor ref=' . $ref . ' nor ref_ext=' . $ref_ext;
			}
		} else {
			$error ++;
			$errorcode = 'PERMISSION_DENIED';
			$errorlabel = 'User does not have permission for this request';
		}
	}

	if ($error) {
		$objectresp = array(
				'result' => array(
				'result_code' => $errorcode,
				'result_label' => $errorlabel
			)
		);
	}

	return $objectresp;
}


/**
 * Create a propal
 *
 * @param array $authentication
 *        	Array of authentication information
 * @param Propal $propal
 *        	Invoice
 * @return array Array result
 */
function createPropal($authentication, $propal)
{
	global $db, $conf, $langs;

	$now = dol_now();
	dol_syslog("Function: createPropal login=" . $authentication['login'] . " thirdparty_id=" . $propal->thirdparty_id);

	// status
	// Propal::STATUS_DRAFT, Propal::STATUS_VALIDATED, Propal::STATUS_SIGNED, Propal::STATUS_NOTSIGNED, Propal::STATUS_BILLED


	if ($authentication['entity'])
		$conf->entity = $authentication['entity'];

		// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);

	// Check parameters
	if (empty($propal['thirdparty_id'])) {
		$error ++;
		$errorcode = 'KO';
		$errorlabel = "Propal thirdparty_id is mandatory.";
	}

	if (! $error) {
		$new_propal = new Propal($db);
		$new_propal->socid = $propal['thirdparty_id'];
		$new_propal->type = $propal['type']; // --- 0 pour standard
		$new_propal->ref_client = $propal['ref_ext'];
		//$new_propal->date = dol_stringtotime($propal['date'], 'dayrfc');
		$new_propal->date = $now;
		$new_propal->note_private = $propal['note_private'];
		$new_propal->note_public = $propal['note_public'];
		$new_propal->statut = Propal::STATUS_DRAFT; // We start with status draft
		//$new_propal->statut = Propal::STATUS_VALIDATED;
		$new_propal->date_creation = $now;
		$new_propal->duree_validite = '365'; // validité de l'offre = 1 an

		// ---- Get the 1st account if empty
		if (empty($propal['account_id']) || $propal['account_id'] == - 1) {
			$propal['account_id'] = 1;
		}
		$account = new Account($db);
		$account->fetch($propal['account_id']);

		// take mode_reglement and cond_reglement from thirdparty
		$soc = new Societe($db);
		$res = $soc->fetch($new_propal->socid);
		if ($res > 0) {
			$new_propal->mode_reglement_id = ! empty($propal['payment_mode_id']) ? $propal['payment_mode_id'] : $soc->mode_reglement_id;
			if (! empty($soc->fk_account)) {
				$new_propal->fk_account = ! empty($propal['account_id']) ? $propal['account_id'] : $soc->fk_account;
			} else
				if (! empty($account->id)) {
					$new_propal->fk_account = ! empty($propal['account_id']) ? $propal['account_id'] : $account->id;
				}
			$new_propal->cond_reglement_id = ! empty($propal['cond_reglement_id']) ? $propal['cond_reglement_id'] : $soc->cond_reglement_id;
		} else {
			$new_propal->mode_reglement_id = $propal['payment_mode_id'];
			$new_propal->cond_reglement_id = $propal['cond_reglement_id'];
			if (! empty($account->id)) {
				$new_propal->fk_account = ! empty($propal['account_id']) ? $propal['account_id'] : $account->id;
			}
		}

		// force mode reglement
		if(empty($new_propal->mode_reglement_id))
			$new_propal->mode_reglement_id = 0;

        // Search idwharehouse
        /*if (! empty($conf->stock->enabled)) {
			$entrepot_id = new OerisProductStockEntrepot($db);
			$mouvement = new MouvementStock($db);
			$fk_entrepot = 0;
		}*/

        // Trick because nusoap does not store data with same structure if there is one or several lines
        $arrayoflines = array();
		if (isset($propal['lines']['line'][0]))
			$arrayoflines = $propal['lines']['line'];
		else
			if (isset($propal['lines']))
				$arrayoflines = $propal['lines'];

		if (count($arrayoflines) > 0) {
			foreach ($arrayoflines as $key => $line) {
				// $key can be 'line' or '0','1',...
				$newline = new PropaleLigne($db);
				$newline->product_type = $line['type'];
				$newline->desc = $line['desc'];
				$newline->fk_product = $line['fk_product'];
				$newline->tva_tx = $line['vat_rate'];
				$newline->qty = $line['qty'];
				$newline->subprice = $line['unitprice'];
				$newline->total_ht = $line['total_net'];
				$newline->total_tva = $line['total_vat'];
				$newline->total_ttc = $line['total'];
				$newline->date_start = dol_stringtotime($line['date_start']);
				$newline->date_end = dol_stringtotime($line['date_end']);
				$newline->fk_product = $line['product_id'];

		/*		if (! empty($conf->stock->enabled) && empty($fk_entrepot)) {
					if ($entrepot_id->getFirstAvailableWharehouse($line['product_id']) > 0) {
						$fk_entrepot = $entrepot_id->fk_entrepot;
						$mouvement->livraison($fuser, $line['product_id'], $fk_entrepot, $line['qty'], $line['unitprice']);
					}
				}*/
				$new_propal->lines[] = $newline;
			}
		}
		// var_dump($newobject->date_lim_reglement); exit;
		// var_dump($invoice['lines'][0]['type']);

		$db->begin();

		//$result = $new_propal->create($fuser, 0, dol_stringtotime($invoice['date_due'], 'dayrfc'));
		$result = $new_propal->create($fuser);
		if ($result < 0) {
			$error ++;
		}

		if (! $error) {
			// ---- Ajoute le contact de facturation
			// ---- id = Rowid, contact_source = external, contact_type=
			//$lesTypes = $new_propal->liste_type_contact($propal["contact_source"], 'position', 0, 1, 'BILLING');
			$lesTypes = $new_propal->liste_type_contact('external', 'position', 0, 1, 'BILLING');
			foreach ($lesTypes as $key => $value) {
				$type = $key;
			}
			$result = $new_propal->add_contact($propal['contact_id'], $type, 'external');
			if ($result < 0) {
				$error ++;
				$errorcode = "INFO";
				$errorlabel = "Impossible d'ajouter un contact a cette facture";
			}
		}

		if (! $error && $propal['status'] == Propal::STATUS_VALIDATED) // We want invoice to have status validated
		{
			//$result = $new_propal->validate($fuser, $fk_entrepot);
			$result = $new_propal->valid($fuser);
			if ($result < 0) {
				$error ++;
			}
			//$result = $new_propal->set_draft($fuser, $fk_entrepot); // passage de la facture en brouillon
			$result = $new_propal->set_draft($fuser); // passage de la facture en brouillon
			if ($result < 0) {
				$error ++;
			}
		}

		if (! $error) {
			$db->commit();
			$objectresp = array(
				'result' => array(
				'result_code' => 'OK',
				'result_label' => ''
			),
				'propal' => array(
					'id' => $new_propal->id,
					'ref' => $new_propal->ref,
					'ref_ext' => $new_propal->ref_client,
					'total_ttc' => ''
				)
			);
		} else {
			$db->rollback();
			$error ++;
			$errorcode = 'KO';
			$errorlabel = $new_propal->error;
			dol_syslog("Function: createPropal error while creating" . $errorlabel);
		}
	}

	if ($error) {
		$objectresp = array(
			'result' => array(
				'result_code' => $errorcode,
				'result_label' => $errorlabel
			)
		);
	}

	return $objectresp;
}

/**
 * Create an invoice from an order
 *
 * @param array $authentication
 *        	Array of authentication information
 * @param string $id_order
 *        	id of order to copy invoice from
 * @param string $ref_order
 *        	ref of order to copy invoice from
 * @param string $ref_ext_order
 *        	ref_ext of order to copy invoice from
 * @param string $id_invoice
 *        	invoice id
 * @param string $ref_invoice
 *        	invoice ref
 * @param string $ref_ext_invoice
 *        	invoice ref_ext
 * @return array Array result
 */
function createInvoiceFromOrder($authentication, $id_order = '', $ref_order = '', $ref_ext_order = '', $contact_id = '')
{
	global $user, $db, $conf, $langs;

	$now = dol_now();

	dol_syslog("Function: createInvoiceFromOrder login=" . $authentication['login'] . " id=" . $id_order . ", ref=" . $ref_order . ", ref_ext=" . $ref_ext_order);

	if ($authentication['entity'])
		$conf->entity = $authentication['entity'];

		// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);
	$user = $fuser;
	// Check parameters
	if (empty($id_order) && empty($ref_order) && empty($ref_ext_order)) {
		$error ++;
		$errorcode = 'KO';
		$errorlabel = "order id or ref or ref_ext is mandatory. id_order=" . $id_order . ", ref_order=" . $ref_order . ", ref_ext_order=" . $ref_ext_order;
	}
	/*
	 * else if (empty($id_invoice) && empty($ref_invoice) && empty($ref_ext_invoice)) {
	 * $error++; $errorcode='KO'; $errorlabel="invoice id or ref or ref_ext is mandatory.";
	 * }
	 */

	// ////////////////////
	if (! $error) {
		$fuser->getrights();

		if ($fuser->rights->commande->lire) {
			require_once (DOL_DOCUMENT_ROOT . "/commande/class/commande.class.php");
			$order = new Commande($db);
			$result = $order->fetch($id_order, $ref_order, $ref_ext_order);
			if ($result > 0) {
				// Security for external user
				if ($socid && ($socid != $order->socid)) {
					$error ++;
					$errorcode = 'PERMISSION_DENIED';
					$errorlabel = $order->socid . 'User does not have permission for this request';
				}

				if (! $error) {

					$newobject = new Facture($db);
					$result = $newobject->createFromOrder($order);
					if ($result < 0) {
						$error ++;
						dol_syslog("Webservice server_invoice:: invoice creation from order failed", LOG_ERR);
						$errorcode = 'ERROR';
						$errorlabel = 'Webservice oeris_invoice:: invoice creation from order failed (' . $result . '). Facture->createFromOrder says ' . $newobject->error;
					}

					// ---- Ajoute le contact de facturation
					// ---- id = Rowid, contact_source = external, contact_type=
					$lesTypes = $newobject->liste_type_contact("external", 'position', 0, 1, 'BILLING');
					foreach ($lesTypes as $key => $value) {
						$type = $key;
					}
					/*
					 * $result = $newobject->add_contact($contact_id, $type, "external");
					 * if ($result < 0)
					 * {
					 * $error++;
					 * $errorcode="INFO";
					 * $errorlabel="Impossible d'ajouter un contact a cette facture";
					 * }
					 */

					// ---- Ajoute une note publique contenant la réf du ticket et le sujet
					/*
					 * $result = $newobject->add_contact($contact_id, $type, "external");
					 * if ($result < 0)
					 * {
					 * $error++;
					 * $errorcode="INFO";
					 * $errorlabel="Impossible d'ajouter un contact a cette facture";
					 * }
					 */

					$result = $newobject->validate($fuser);
					if ($result >= 0) {
						// Define output language
						$outputlangs = $langs;
						$ret = $newobject->fetch($newobject->id); // Reload to get new records
						$newobject->generateDocument($newobject->modelpdf, $outputlangs);
					} else {
						$error ++;
						dol_syslog("Webservice server_invoice:: can't validate invoice", LOG_ERR);
						$errorcode = 'ERROR';
						$errorlabel = 'Webservice oeris_invoice:: can\'t validate invoice (' . $result . '). WS says ' . $newobject->error;
					}
				}
			} else {
				$error ++;
				$errorcode = 'NOT_FOUND';
				$errorlabel = 'Object not found for id=' . $id_order . ' nor ref=' . $ref_order . ' nor ref_ext=' . $ref_ext_order;
			}
		} else {
			$error ++;
			$errorcode = 'PERMISSION_DENIED';
			$errorlabel = 'User does not have permission for this request';
		}
	}

	if ($error) {
		$objectresp = array(
		'result' => array(
		'result_code' => $errorcode,
		'result_label' => $errorlabel
		)
		);
	} else {
		$objectresp = array(
		'result' => array(
		'result_code' => 'OK',
		'result_label' => ''
		),
		'invoice' => array(
		'id' => $newobject->id,
		'ref' => $newobject->ref,
		'ref_ext' => $newobject->ref_ext,
		'total_ttc' => ''
		)
		);
	}

	return $objectresp;
}

/**
 * Uddate an invoice, only change the state of an invoice
 *
 * @param array $authentication
 *        	Array of authentication information
 * @param Facture $invoice
 *        	Invoice
 * @return array Array result
 */
function updateInvoice($authentication, $invoice)
{
	global $db, $conf, $langs;

	dol_syslog("Function: updateInvoice login=" . $authentication['login'] . " id=" . $invoice['id'] . ", ref=" . $invoice['ref'] . ", ref_ext=" . $invoice['ref_ext']);

	if ($authentication['entity'])
		$conf->entity = $authentication['entity'];

		// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);

	// Check parameters
	if (empty($invoice['id']) && empty($invoice['ref']) && empty($invoice['ref_ext'])) {
		$error ++;
		$errorcode = 'KO';
		$errorlabel = "Invoice id or ref or ref_ext is mandatory.";
	}

	if (! $error) {

		if (isset($invoice['delete_item_id']) && ! empty($invoice['delete_item_id'])) {

			$objectfound = false;

			$object = new Facture($db);
			$result = $object->fetch($invoice['id'], $invoice['ref'], $invoice['ref_ext'], '');

			if (! empty($object->id)) {

				$objectfound = true;
				if ($object->statut != Facture::STATUS_CLOSED) {

					$result = $object->set_draft($fuser, $fk_entrepot);

					// ---- Supprime un objet du stock
					if (! empty($conf->stock->enabled)) {
						$objectLine = new FactureLigne($db);
						$resultLine = $objectLine->fetch($invoice['delete_item_id']);
						$entrepot_id = new OerisProductStockEntrepot($db);
						$mouvement = new MouvementStock($db);
						$fk_entrepot = 0;
						if ($entrepot_id->getFirstAvailableWharehouse($objectLine->fk_product) > 0) {
							$fk_entrepot = $entrepot_id->fk_entrepot;
						}
						$retmouvement = $mouvement->reception($fuser, $objectLine->fk_product, $fk_entrepot, $objectLine->qty, $objectLine->subprice);
					}
					// ---- Supprime un objet de la commande
					$result = $object->deleteline($invoice['delete_item_id']);

					$result = $object->validate($fuser);

					if ($result < 0) {
						$errorcode = 'KO';
						$errorlabel = $object->error;
						$objectresp = array(
						'result' => array(
						'result_code' => $errorcode,
						'result_label' => $errorlabel
						)
						);
						return $objectresp;
					} else {
						// ---- Genere la facture PDF
						$outputlangs = $langs;
						$ret = $object->fetch($object->id); // Reload to get new records
						$object->generateDocument($object->modelpdf, $outputlangs);

						$errorcode = 'OK';
						$errorlabel = 'item ' . $invoice['delete_item_id'] . ' deleted';
						$objectresp = array(
						'result' => array(
						'result_code' => $errorcode,
						'result_label' => $errorlabel,
						'total_ttc' => $object->total_ttc
						)
						);
						return $objectresp;
					}
				} else {
					$errorcode = 'KO';
					$errorlabel = 'Invoice already payed';
					$objectresp = array(
					'result' => array(
					'result_code' => $errorcode,
					'result_label' => $errorlabel
					)
					);
					return $objectresp;
				}
			} else {
				$errorcode = 'KO';
				$errorlabel = $object->error;
				$objectresp = array(
				'result' => array(
				'result_code' => $errorcode,
				'result_label' => $errorlabel
				)
				);
				return $objectresp;
			}
		} else {

			$objectfound = false;

			$object = new Facture($db);
			$result = $object->fetch($invoice['id'], $invoice['ref'], $invoice['ref_ext'], '');

			if (! empty($object->id) && $object->statut != Facture::STATUS_CLOSED) {

				$objectfound = true;
				$result = $object->set_draft($fuser);

				// Trick because nusoap does not store data with same structure if there is one or several lines
				$arrayoflines = array();
				if (isset($invoice['lines']['line'][0]))
					$arrayoflines = $invoice['lines']['line'];
				else
					if (isset($invoice['lines']))
						$arrayoflines = $invoice['lines'];

				if (count($arrayoflines) > 0) {
					foreach ($arrayoflines as $key => $line) {

						if (! empty($conf->stock->enabled)) {
							$objectLine = new FactureLigne($db);
							$entrepot_id = new OerisProductStockEntrepot($db);
							$mouvement = new MouvementStock($db);
							$fk_entrepot = 0;
						}

						// ---- Permet la mise a jour des quantit øs pour une meme r øf ørence de produit
						$currentqty = 0;
						foreach ($object->lines as $currentline) {
							if (($currentline->fk_product == $line['product_id'] && $line['product_id'] > 0) || ($currentline->product_ref == $line['product_ref'] && $line['product_ref'] != '')) {
								$resultLine = $objectLine->fetch($currentline->rowid);
								if ($entrepot_id->getFirstAvailableWharehouse($resultLine->fk_product) > 0) {
									$fk_entrepot = $entrepot_id->fk_entrepot;
								}

								// ---- Supprime la ligne produit
								if ($line['total_qty'] > 0) {
									if ($currentline->desc != '' && $currentline->desc == trim($line['desc'])) {
										$result = $object->deleteline($currentline->rowid);
										if (! empty($conf->stock->enabled)) {
											$mouvement->reception($fuser, $currentline->fk_product, $fk_entrepot, $currentline->qty, $currentline->subprice);
										}
										$currentqty = 0;
									} else {
										$currentqty = 0;
									}
								} else {
									$result = $object->deleteline($currentline->rowid);
									if (! empty($conf->stock->enabled)) {
										$mouvement->reception($fuser, $currentline->fk_product, $fk_entrepot, $currentline->qty, $currentline->subprice);
									}
									$currentqty += $currentline->qty;
								}
							}
						}
						// exit;
						// ---- Si la quantit ø est a 0, laisse le produit pr øsent mais sera factur ø a 0 éo
						if ($currentqty < 0) {
							$currentqty = 0;
						}

						// ---- Ajoute le nouveau produit ou met a jour la quantit ø en prenant en compte l'historique
						$newproduct = new Product($db);
						$newproduct->fetch($line['product_id'], $line['product_ref'], '', '');

						$newline = new FactureLigne($db);
						$newline->product_type = $newproduct->type;
						$newline->desc = trim($line['desc']);
						$newline->fk_product = $newproduct->id;
						$newline->tva_tx = $newproduct->tva_tx;
						$newline->qty = ($line['qty'] + $currentqty);
						$newline->subprice = $newproduct->price;
						$newline->total_ht = ($newproduct->price * ($line['qty'] + $currentqty));
						$newline->total_tva = ($newproduct->price_ttc * ($line['qty'] + $currentqty)) - ($newproduct->price * ($line['qty'] + $currentqty));
						$newline->total_ttc = ($newproduct->price_ttc * ($line['qty'] + $currentqty));
						$newline->fk_facture = $object->id;

						if (! empty($conf->stock->enabled) && $entrepot_id->getFirstAvailableWharehouse($newproduct->id) > 0) {
							$fk_entrepot = $entrepot_id->fk_entrepot;
							$mouvement->livraison($fuser, $newproduct->id, $fk_entrepot, ($line['qty'] + $currentqty), $newproduct->price);
						}

						$object->total_ht = $object->total_ht + ($newproduct->price * ($line['qty'] + $currentqty));
						$object->total_ttc = $object->total_ttc + ($newproduct->price_ttc * ($line['qty'] + $currentqty));
						$object->total_tva = $object->total_tva + ($newproduct->price_ttc * ($line['qty'] + $currentqty)) - ($newproduct->price * ($line['qty'] + $currentqty));
						$object->update($fuser);

						if ($newline->insert() < 0) {
							$error ++;
							$errorcode = 'KO';
							$errorlabel = $newline->error;
						}
					}
				}

				// ---- Ajout la ref du SAV et le sujet
				// $result=$object->fetch($invoice['id'],$invoice['ref'],$invoice['ref_ext'], '');
				// $object->note_public=$invoice['note_public'];
				// $object->update($fuser);

				if ($error) {
					$objectresp = array(
					'result' => array(
					'result_code' => $errorcode,
					'result_label' => $errorlabel
					)
					);
					return $objectresp;
				}

				$db->begin();

				if (isset($invoice['status'])) {
					if ($invoice['status'] == Facture::STATUS_DRAFT) {
						$result = $object->set_draft($fuser);
					}
					if ($invoice['status'] == Facture::STATUS_VALIDATED) {

						if (! empty($invoice['payment_mode_id'])) {
							$object->mode_reglement_id = $invoice['payment_mode_id'];
							$object->update($fuser);
						}
						if (! empty($invoice['cond_reglement_id'])) {
							$object->cond_reglement_id = $invoice['cond_reglement_id'];
							$object->update($fuser);
						}
						if (! empty($invoice['account_id'])) {
							$object->setBankAccount($invoice['account_id']);
						}

						$result = $object->validate($fuser);
					}
					if ($invoice['status'] == Facture::STATUS_CLOSED) // STATUS_CLOSED = 2
{
						if ($object->cond_reglement_code != 'MANUEL' && $object->mode_reglement_code != '') {
							$result = $object->validate($fuser);
							// $result = $object->set_paid($fuser);

							$amounts[$object->id] = $object->total_ttc;
							// Creation of payment line
							$paiement = new Paiement($db);
							$paiement->datepaye = date("Y-m-d H:i:s");
							$paiement->amounts = $amounts; // Array with all payments dispatching
							$paiement->multicurrency_amounts = array(); // Array with all payments dispatching
							$paiement->paiementid = dol_getIdFromCode($db, $object->mode_reglement_code, 'c_paiement');
							$paiement->num_paiement = '';
							$paiement->note = '';
							$paiement_id = $paiement->create($fuser, 1);
							if ($paiement_id < 0) {
								$errorcode = 'KO';
								$errorlabel = 'Creation du paiement impossible : ' . $paiement->error;
								$error ++;
								$objectresp = array(
								'result' => array(
								'result_code' => $errorcode,
								'result_label' => $errorlabel
								)
								);
								return $objectresp;
							}
							// ---- Si un compte existe ajoute le paiement au compte
							if ($object->fk_account > 0) {
								$result = $paiement->addPaymentToBank($fuser, 'payment', '', $object->fk_account, '', '');
								if ($result < 0) {
									$errorcode = 'KO';
									$errorlabel = 'Creation de l\'entr øe banque impossible : ' . $paiement->error;
									$error ++;
									$objectresp = array(
									'result' => array(
									'result_code' => $errorcode,
									'result_label' => $errorlabel
									)
									);
									return $objectresp;
								}
							} else {
								// ---- Utilisation du compte #1 si existant
								$result = $paiement->addPaymentToBank($fuser, 'payment', '', 1, '', '');
								if ($result < 0) {
									$errorcode = 'KO';
									$errorlabel = 'Creation de l\'entr øe banque impossible : ' . $paiement->error;
									$error ++;
									$objectresp = array(
									'result' => array(
									'result_code' => $errorcode,
									'result_label' => $errorlabel
									)
									);
									return $objectresp;
								}
							}

							$result = $object->set_paid($fuser);
						}
					}
					if ($invoice['status'] == Facture::STATUS_ABANDONED)
						$result = $object->set_canceled($fuser, $invoice->close_code, $invoice->close_note);
				}
			}

			if ((! $error) && ($objectfound)) {
				$db->commit();

				// ---- Genere la facture PDF
				$outputlangs = $langs;
				$ret = $object->fetch($object->id); // Reload to get new records
				$object->generateDocument($object->modelpdf, $outputlangs);

				$objectresp = array(
				'result' => array(
				'result_code' => 'OK',
				'result_label' => ''
				),
				'invoice' => array(
				'id' => $object->id,
				'ref' => $object->ref,
				'ref_ext' => $object->ref_ext,
				'total_ttc' => $object->total_ttc
				)
				);
			} elseif ($objectfound) {
				$db->rollback();
				$error ++;
				$errorcode = 'KO';
				$errorlabel = $object->error;
			} else {
				$error ++;
				$errorcode = 'NOT_FOUND';
				$errorlabel = 'An unpayed invoice with id=' . $invoice['id'] . ' ref=' . $invoice['ref'] . ' ref_ext=' . $invoice['ref_ext'] . ' cannot be found';
			}
		}

		if ($error) {
			$objectresp = array(
			'result' => array(
			'result_code' => $errorcode,
			'result_label' => $errorlabel
			)
			);
		}
	}

	return $objectresp;
}

// Return the results.
$server->service(file_get_contents("php://input"));

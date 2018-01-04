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
 *       \file       htdocs/dolinrt/oeris_searchcontact.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once("../master.inc.php");
require_once(NUSOAP_PATH.'/nusoap.php');		// Include SOAP
require_once(DOL_DOCUMENT_ROOT."/core/lib/ws.lib.php");
//require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';


dol_syslog("Call Contact webservices interfaces");

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
$server->configureWSDL('WebServicesOerisSearchContact',$ns);
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
      'entity' => array('name'=>'entity','type'=>'xsd:string'),
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

$contact_fields = array(
	'id' => array('name'=>'id','type'=>'xsd:string'),
	'lastname' => array('name'=>'lastname','type'=>'xsd:string'),
	'firstname' => array('name'=>'firstname','type'=>'xsd:string'),
	'address' => array('name'=>'address','type'=>'xsd:string'),
	'zip' => array('name'=>'zip','type'=>'xsd:string'),
	'town' => array('name'=>'town','type'=>'xsd:string'),
	'country_id' => array('name'=>'country_id','type'=>'xsd:string'),
	'societe' => array('name'=>'societe','type'=>'xsd:string'),
  'socid' => array('name'=>'socid','type'=>'xsd:string'),
	'status' => array('name'=>'status','type'=>'xsd:string'),
	'phone_pro' => array('name'=>'phone_pro','type'=>'xsd:string'),
	'fax' => array('name'=>'fax','type'=>'xsd:string'),
	'phone_perso' => array('name'=>'phone_perso','type'=>'xsd:string'),
	'phone_mobile' => array('name'=>'phone_mobile','type'=>'xsd:string'),
	'email' => array('name'=>'email','type'=>'xsd:string'),
	'default_lang' => array('name'=>'default_lang','type'=>'xsd:string'),
	'note' => array('name'=>'note','type'=>'xsd:string'),
	'no_email' => array('name'=>'no_email','type'=>'xsd:string'),
	'civility_id' => array('name'=>'civility_id','type'=>'xsd:string'),
	'poste' => array('name'=>'poste','type'=>'xsd:string')
	//...
);

// Define other specific objects
$server->wsdl->addComplexType(
    'contact',
    'complexType',
    'struct',
    'all',
    '',
	$contact_fields
);
$server->wsdl->addComplexType(
    'contacts',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref'=>'SOAP-ENC:arrayType','wsdl:arrayType'=>'tns:contact[]')
    ),
    'tns:contact'
);



// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc='rpc';       // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse='encoded';   // encoded/literal/literal wrapped
// Better choice is document/literal wrapped but literal wrapped not supported by nusoap.


// Register WSDL
$server->register(
    'getContact',
    // Entry values
    array('authentication'=>'tns:authentication','term'=>'xsd:string','filter'=>'xsd:string','strict'=>'xsd:int'),
    // Exit values
    array('result'=>'tns:result','contacts'=>'tns:contacts'),
    $ns,
    $ns.'#getContact',
    $styledoc,
    $styleuse,
    'WS to get a contact'
);




/**
 * Get Contact
 *
 * @param	array		$authentication		Array of authentication information
 * @param	string		$term			  Search Term
 * @param	string		$filter			Filter on a specific column
 * @return	mixed
 */
function getContact($authentication,$term,$filter,$strict=0)
{
    global $db,$conf,$langs;

    dol_syslog("Function: getContact login=".$authentication['login']." $term=".$term." $filter=".$filter);

    if ($authentication['entity']) $conf->entity=$authentication['entity'];

    // Init and check authentication
    $objectresp=array();
    $errorcode='';$errorlabel='';
    $error=0;
    $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
    // Check parameters
    if (! $error && $term && !$filter)
    {
        $error++;
        $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter filter must be provided.";
    }
    // Check parameters
    if (! $error && !$term && $filter)
    {
        $error++;
        $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter term must be provided.";
    }

    if (! $error)
    {
        $fuser->getrights();
        if ($term && ($filter == 'societe' || $filter == 'email' || $filter == 'phone_pro'))
        {
          if ($term && ($filter == 'societe' || $filter == 'phone_pro'))  {  $sql = "SELECT s.rowid, s.nom as lastname, s.email as email, s.phone as phone_pro,";  }
          else  {  $sql = "SELECT sp.rowid, sp.lastname as lastname, sp.firstname as firstname, sp.email as email, s.phone as phone_pro, sp.phone_mobile as phone_mobile, sp.phone_perso as phone_perso,";  }
      		$sql.= " s.address, s.status, s.zip, s.town,";
      		$sql.= " s.fk_pays as country_id,";
          $sql.= " s.nom as socname, s.rowid as socid";
      		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."socpeople as sp";
          if ($strict) {
            if ($term && $filter == 'societe')    $sql.= " WHERE sp.fk_soc = s.rowid AND s.nom = '". $term."' GROUP BY s.nom";
            if ($term && $filter == 'email')    $sql.= " WHERE sp.fk_soc = s.rowid AND (sp.email = '". $term."'  OR s.email = '". $term."') GROUP BY s.nom";
            if ($term && $filter == 'phone_pro')    $sql.= " WHERE sp.fk_soc = s.rowid AND s.phone = '". $term."' GROUP BY s.nom";
          } else {
            if ($term && $filter == 'societe')    $sql.= " WHERE sp.fk_soc = s.rowid AND s.nom like '". $term."%' GROUP BY s.nom";
            if ($term && $filter == 'email')    $sql.= " WHERE sp.fk_soc = s.rowid AND (sp.email like '%". $term."%'  OR s.email like '%". $term."%') GROUP BY s.nom";
            if ($term && $filter == 'phone_pro')    $sql.= " WHERE sp.fk_soc = s.rowid AND s.phone like '". $term."%' GROUP BY s.nom";
          }
        }
        else
        {
          $sql = "SELECT c.rowid, c.fk_soc, c.ref_ext, c.civility as civility_id, c.lastname, c.firstname,";
      		$sql.= " c.address, c.statut, c.zip, c.town,";
      		$sql.= " c.fk_pays as country_id,";
      		$sql.= " c.fk_departement,";
      		$sql.= " c.birthday,";
      		$sql.= " c.poste, c.phone_mobile as phone_mobile, c.phone_perso, s.phone as phone_pro, c.fax, c.email, c.jabberid, c.skype,";
          $sql.= " s.nom as socname, s.rowid as socid,";
      		$sql.= " c.priv, c.note_private, c.note_public, c.default_lang, c.no_email, c.canvas,";
      		$sql.= " c.import_key";
      		$sql.= " FROM ".MAIN_DB_PREFIX."socpeople as c, ".MAIN_DB_PREFIX."societe as s";
          if ($term && $filter == 'prenom')   $sql.= " WHERE c.fk_soc = s.rowid AND c.firstname like '". $term."%'";
          if ($term && $filter == 'nom')      $sql.= " WHERE c.fk_soc = s.rowid AND c.lastname like '". $term."%'";
          if ($term && $filter == 'email')    $sql.= " WHERE c.fk_soc = s.rowid AND c.email like '%". $term."%'";
          if ($term && $filter == 'phone_mobile')    $sql.= " WHERE c.fk_soc = s.rowid AND c.phone_mobile like '". $term."%'";
          if ($term && $filter == 'phone_perso')    $sql.= " WHERE c.fk_soc = s.rowid AND c.phone_perso like '". $term."%'";
        }

        //print $sql;


    		$resql=$db->query($sql);
        if ($resql)
        {
            $num=$db->num_rows($resql);

             // Only internal user who have contact read permission
            // Or for external user who have contact read permission, with restrict on societe_id
  	        if ( $fuser->rights->societe->contact->lire && !$fuser->societe_id ||
                 ( $fuser->rights->societe->contact->lire && ($fuser->societe_id == $contact->socid)) )
            {
                $i=0;
                while ($i < $num)
                {

                    $contact=$db->fetch_object($resql);
                    $contact_result_fields[] =array(
      	            	'id' => $contact->rowid,
      	            	'lastname' => $contact->lastname,
      	            	'firstname' => $contact->firstname,
      	            	'address' => $contact->useraddress,
      	            	'zip' => $contact->userzip,
      	            	'town' => $contact->usertown,
      	            	'country_id' => $contact->country_id,
      	            	'societe' => $contact->socname,
                      'socid' => $contact->socid,
      	            	'status' => $contact->statut,
      	            	'phone_pro' => $contact->phone_pro,
      	            	'fax' => $contact->fax,
      	            	'phone_perso' => $contact->phone_perso,
      	            	'phone_mobile' => $contact->phone_mobile,
      	            	'email' => $contact->email,
      	            	'default_lang' => $contact->default_lang,
      	            	'note' => $contact->note,
      	            	'no_email' => $contact->no_email,
      	            	'civility_id' => $contact->civility_id,
                  		'poste' => $contact->poste
                  	);

                    $i++;
                }

                if(!isset($contact_result_fields) || count($contact_result_fields) == 0 )
                {
                  $error++;
                  $errorcode='INFO'; $errorlabel=' Nothing found in DB with term='.$term.' and filter='.$filter.'.';
                }
                else
                {
                  // Create
                  $objectresp = array(
  			    	      'result'=>array('result_code'=>'OK', 'result_label'=> ''),
  			            'contacts'=>$contact_result_fields
                  );
                }
            }
            else
  	        {
  	            $error++;
  	            $errorcode='PERMISSION_DENIED'; $errorlabel='User does not have permission for this request';
  	        }
        }
        else
        {
           $error++;
           $errorcode='ERROR'; $errorlabel=' Request : '.$sql.'. '.$db->error();
        }

    }

    if ($error)
    {
        $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
    }

    return $objectresp;
}

// Return the results.
$server->service(file_get_contents("php://input"));

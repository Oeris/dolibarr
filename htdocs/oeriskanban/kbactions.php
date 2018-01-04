<?php
/* Copyright (C) 2016      Neil Orley		 <neil.orley@oeris.fr>
 * 
 * Largely inspired by the great work of : 
 * 
 * Copyright (C) 2001-2002  Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010  Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2012       Vinícius Nogueira    <viniciusvgn@gmail.com>
 * Copyright (C) 2014       Florian Henry    	 <florian.henry@open-cooncept.pro>
 * Copyright (C) 2015       Jean-François Ferry	<jfefe@aternatik.fr>
 *
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
 *	\file       htdocs/oerisbank/bankentries.php
 *	\ingroup    banque
 *	\brief      Datailed list of bank transactions
 */
 
require('../main.inc.php');
dol_include_once('/oeriskanban/class/JsonRPC/Client.php');
dol_include_once('/oeriskanban/class/JsonRPC/HttpClient.php');
dol_include_once('/oeriskanban/class/JsonRPC/Request/RequestBuilder.php');
dol_include_once('/oeriskanban/class/JsonRPC/Response/ResponseParser.php');
dol_include_once('/oeriskanban/class/JsonRPC/Exception/ConnectionFailureException.php');
dol_include_once('/oeriskanban/class/JsonRPC/Exception/AccessDeniedException.php');
dol_include_once('/oeriskanban/class/JsonRPC/Validator/JsonFormatValidator.php');
dol_include_once('/oeriskanban/class/JsonRPC/Exception/InvalidJsonFormatException.php');
dol_include_once('/oeriskanban/class/OerisKanban.php');

//---- Fichier de langue
$langs->load("oeriskanboard@oeriskanban");                                                                       
$action = GETPOST('action', 'alpha');

//---- Test : curl -u "jsonrpc:4d1ecde3a6ab30dae15d905e52bbcdca2fabc52171f19f6deb9437deb6cf" -d '{"jsonrpc": "2.0", "method": "getAllProjects", "id": 2087700490}' https://kanboard.oeris.fr/jsonrpc.php
//---- Test : curl -u "jsonrpc:4d1ecde3a6ab30dae15d905e52bbcdca2fabc52171f19f6deb9437deb6cf" -d '{"jsonrpc": "2.0", "method": "getAllTasks", "id": 2087700490, "params": { "project_id": 1, "status_id": 1 }}' https://kanboard.oeris.fr/jsonrpc.php
//---- Test : curl -u "jsonrpc:4d1ecde3a6ab30dae15d905e52bbcdca2fabc52171f19f6deb9437deb6cf" -d '{"jsonrpc": "2.0", "method": "getAllSubtasks", "id": 2087700490, "params": { "task_id": 1 }}' https://kanboard.oeris.fr/jsonrpc.php
//---- Test : curl -u "jsonrpc:4d1ecde3a6ab30dae15d905e52bbcdca2fabc52171f19f6deb9437deb6cf" -d '{"jsonrpc": "2.0", "method": "getBoard", "id": 2087700490, "params": { "project_id": 1 }}' https://kanboard.oeris.fr/jsonrpc.php
$client = new JsonRPC\Client('https://kanboard.oeris.fr/jsonrpc.php');
$client->authentication('jsonrpc', '4d1ecde3a6ab30dae15d905e52bbcdca2fabc52171f19f6deb9437deb6cf');

$kanboard = new Oeris\Kanboard($client->getAllProjects());
$kanboard->client = $client;

switch($action) {
    case 'showAllProjects'  :  print $kanboard->showAllProjects();      break;
    case 'showAllTasks'     :  print $kanboard->showAllTasks();         break;
    case 'showAllSubTasks'  :  print $kanboard->showAllSubTasks();      break;
    case 'showBoardHeader'  :  print $kanboard->showBoardHeader();      break;
}

?>
                                                  
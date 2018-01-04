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


//---- Fichier de langue
$langs->load("oeriskanboard@oeriskanban");
//---- Header
llxHeader('',$langs->trans("oeriskanban") , '', '', 0, 0, array(), array(), '&contextpage='.$_SERVER["PHP_SELF"]);
//---- Barre de titre
print load_fiche_titre($langs->trans("oeriskanban"),'','/oeriskanban/img/kb.png',1);



print '<div class="container-fluid" style="margin-top:20px">';

print '<div class="row">';
print '<div id="content" class="col-sm-12" style="min-height: auto;">';

//---- PANEL OVERVIEW
print '<!-- Overview -->';
print '<div class="panel panel-info">';
print '  <div class="panel-heading">'.$langs->trans("Overview").'<a href="#" style="color: inherit;" class="pull-right" id="refreshOverview"><i class="fa fa-refresh" aria-hidden="true"></i></a></div>';
print '  <div class="panel-body">';
//---- NAV HEADERS
print '     <!-- Nav tabs -->';
print '     <ul id="overviewTab" class="nav nav-tabs" role="tablist">';
print '       <li role="presentation" class="active"><a href="#Projects" aria-controls="Projects" role="tab" data-toggle="tab">'.$langs->trans("AllProjects").'</a></li>';
print '       <li role="presentation"><a href="#Tasks" aria-controls="Tasks" role="tab" data-toggle="tab">'.$langs->trans("AllTasks").'</a></li>';
print '       <li role="presentation"><a href="#SubTasks" aria-controls="SubTasks" role="tab" data-toggle="tab">'.$langs->trans("AllSubTasks").'</a></li>';
print '     </ul>';
//---- END NAV HEADERS
//---- TAB CONTENT
print '     <div class="tab-content">';
print '         <!-- Projects -->';
print '         <div role="tabpanel" class="tab-pane active" id="Projects"></div>';
print '         <!-- Tasks -->';
print '         <div role="tabpanel" class="tab-pane" id="Tasks"></div>';
print '         <!-- SubTasks -->';
print '         <div role="tabpanel" class="tab-pane" id="SubTasks"></div>';
print '     </div>';                                                                                                           
//---- END TAB CONTENT
print '   </div>';
print '</div>';
//---- END PANEL OVERVIEW


//---- PANEL BOARD
print '<!-- Board -->';
print '<div class="panel panel-success">';
print '   <div class="panel-heading">'.$langs->trans("Board").'<a href="#" style="color: inherit;" class="pull-right" id="refreshBoard"><i class="fa fa-refresh" aria-hidden="true"></i></a></div>';
print '   <div class="panel-body">';
print '     <div id="boardProjects" class="col-sm-12" style="min-height: auto;"></div>';

print '   </div>';
print '</div>';
//---- END PANEL BOARD


print '</div>';
print '</div>';

print '</div>';

?>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    
    //---- Store shown tabs to prevent multiple requests
    var tabShown = new Array();
    
    //---- Loader Ajax
    $( "#loading" ).hide();
    $( document ).ajaxStart(function() {
      $( "#loading" ).show();
    });
    $( document ).ajaxStop(function() {
      $( "#loading" ).hide();
    });
    
    //---- Function to load tab html content
    function _kbLoadContent(action,target) {
        $.ajax({ url: '/oeriskanban/kbactions.php',
             data: {action: action},
             type: 'post',
             beforeSend : function(){ 
                  $('<div id="loading"><img style="margin-top:20px" class="img-responsive center-block" src="/oeriskanban/img/gears.gif"></div>').appendTo( $( "#"+target ) ); 
              },
             success: function(output) {
                  $( output ).appendTo( $( "#"+target ) );
                  //---- Store shown tab
                  tabShown.push(target);
                  //---- Load Board Header if not loaded
                  if(tabShown.indexOf('boardProjects') === -1) {
                      _kbLoadContent('showBoardHeader','boardProjects');
                  }
              },
              complete : function(){ 
                  $('#loading').remove(); 
              } 
        });
    }
    
    
    //---- Refresh the page
    $( "#refreshOverview" ).click(function(e) {
    
        //----  stop the execution of link
        e.preventDefault();
        //---- empty shown tabs array
        var tabShown = new Array();
        //---- retrieve the tab id
        var target = $('li.active a[data-toggle="tab"]').attr('aria-controls');
        //---- empty the tab id
        $('#'+target).empty();
        //---- reload the content
        _kbLoadContent('showAll'+target,target);
    });
    
    
    
    
    //---- Load Projects tab html content
    _kbLoadContent('showAllProjects','Projects');
    

    //---- Trigger to know when a tab is visible
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("aria-controls"); 
        
        if(tabShown.indexOf(target) === -1) {
            switch(target) {
                case 'Projects':
                    _kbLoadContent('showAll'+target,target);
                    break;
                case 'Tasks':
                    _kbLoadContent('showAll'+target,target);
                    break;
                case 'SubTasks':
                    _kbLoadContent('showAll'+target,target);
                    break;
                default:
                    //code block
            }
        }                   
    });
});
</script>
<?php
//---- Footer
llxFooter();

                                                  
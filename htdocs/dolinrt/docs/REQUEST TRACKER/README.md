# README #

Intégration de Dolibarr avec Request Tracker

## What is this repository for? ##

* Ces développements permettent d'intégrer une partie des fonctionnalités de Dolibarr a RT

## How do I get set up? ##

###  Configuration et installation ###
* Si besoin d'adapter les paths, modifier le fichier <RT Lib Path>/RT/Generated.pm 
* Ajouter les variables nécessaire dans le <RT::EtcPath>/RT_SiteConfig.pm


```
#!perl

             # Dolibarr WS auth settings
             Set($DolibarrURL, 'https://dolibarr.exemple.org');
             Set($DolibarrKey, '1231445621458745428712176415687467');
             Set($DolibarrLogin, 'John');
             Set($DolibarrPassword, 'P@55w0rd');
             
             # Permet de lier RT a Dolibarr uniquement sur les files listées
             Set(@DolToRTQueues, qw/Audiotronic/);
             
             # WS SearchContact settings : permet de selectionner le nom du champ autocompleté et le filtre correspondant
             #     liste des filtres de recherche possible : prenom, nom, email, societe, phone_mobile, phone_perso
             #     format : 'CF RT' => 'Filtre Dolibarr', ....
             Set($CFandFilter, { 'Nom' => 'nom', 'Prenom' => 'prenom', 'E-mail' => 'email', 'Societe' => 'societe', 'Telephone' => 'phone_mobile', 'Telephone perso' => 'phone_perso', } );
              
             # Permet de faire de l'autocompletion et d'affecter la valeur recue dans 1 seul champ.
             # Sinon l'ensemble des champs définis dans $CFandFilter sont complétés
             Set(@SingleAutocomplete, qw/Societe/);
              
             # Nom du CF contenant les refs de facture et de commande
             Set($CFRefFacture, 'Facture');
             Set($CFRefCommand, 'Commande');
             
             # Type de facturation par file : par defaut le type de facturation est forfaitaire
             Set($BillingTypePerQueue, { 'Audiotronics' => 'OERIS-TX-HORAIRE' } );
```

* Récupérer les fichiers via GIT
NB : l'ensemble des fichiers sont a déposer dans le RT::LocalPath, visible via http(s)://<RT URL>/Admin/Tools/Configuration.html

```
#!bash

            cd <RT::LocalPath>
            git clone https://bitbucket.org/oeris/rt-to-dolibarr.git .
            rm -rf <RT::MasonDataDir>/obj/*
            rm -rf <RT::MasonDataDir>/cache/*
            Redemarrer le serveur HTTP
```

###  Ajout des scrips ###

* Créer le scrip permettant de déclencher l'ajout de la facturation automatique dans le menu "Administration -> Global -> Scrips -> Ajouter"

![scrip.png](https://bitbucket.org/repo/Rdob5B/images/332826584-scrip.png)             
             
Condition personnalisée:

```
#!perl

return 1;
```
             
Programme de préparation d'action personnalisé:

```
#!perl

#---- Récupère le type de facturation par file: par défaut au forfait
my $BillingTypePerQueue = RT->Config->Get('BillingTypePerQueue');

my $CurrentQueue = $self->TicketObj->QueueObj->Name;

#---- Ajoute le nombre d'heures passées a la facture
unless(defined($BillingTypePerQueue->{$CurrentQueue})) {
  $RT::Logger->debug( "[Scrip Facturation#1] La file ".$CurrentQueue." est au forfait" );
  return 0;
}

```
Code d'action personnalisée (commit):


```
#!perl

use SOAP::Lite;
use MIME::Entity;
use HTTP::Cookies;
use HTTP::Message;
use HTTP::Response;
use HTTP::Request;
use Data::Dumper;

#---- Récupère le type de facturation par file: par défaut au forfait
my $BillingTypePerQueue = RT->Config->Get('BillingTypePerQueue');

my $CurrentQueue = $self->TicketObj->QueueObj->Name;

#---- Récupère le numéro de facture
my $ref_facture = $self->TicketObj->FirstCustomFieldValue(RT->Config->Get('CFRefFacture'));
if (!defined($ref_facture)){
  $RT::Logger->error ( "[Scrip Facturation#1] Aucune référence de facture renseignée");
  return 0;
}


#---- Récupère le temps travaillé
#my $HourWorked =  sprintf("%.2f", ($self->TicketObj->TimeWorked()/60));
my $HourWorked =  sprintf("%.2f", ( ($self->TicketObj->TimeWorked() - $self->TransactionObj->OldValue)/60 ));
if ($HourWorked==0){
  $RT::Logger->error ( "[Scrip Facturation#1] Temps travaillé vaut 0" );
  return 0;
}
$RT::Logger->debug( "[Scrip Facturation#1] Temps travaillé = ".$HourWorked );



my $soap = SOAP::Lite->proxy(RT->Config->Get( 'DolibarrURL' ).'/webservices/oeris_invoice.php', timeout => 10);
$soap->serializer->namespaces->{"ns"} = "xmlns:ns";
$soap->autotype( 0 );
$soap->soapversion( '1.1' );
$soap->ns( 'http://www.dolibarr.org/ns/', 'ns' );
$soap->envprefix( 'soapenv' );

my $header = SOAP::Header->type( 'xml' => '' );

my $auth = SOAP::Data->type("ns:authentication")->name('authentication' => \SOAP::Data->value(
                                                                         SOAP::Data->name('dolibarrkey')->value(RT->Config->Get( 'DolibarrKey' )),
                                                                         SOAP::Data->name('sourceapplication')->value('Request Tracker'),
                                                                         SOAP::Data->name('login')->value(RT->Config->Get( 'DolibarrLogin' )),
                                                                         SOAP::Data->name('password')->value(RT->Config->Get( 'DolibarrPassword' )),
                                                                         SOAP::Data->name('entity')->value('')
                                                                        )
                                                );

my $invoice = SOAP::Data->type("ns:invoice")->name('invoice' => \SOAP::Data->value(
                                                                        SOAP::Data->type("xsd:string")->name('ref')->value($ref_facture),
                                                                        SOAP::Data->type("ns:LinesArray2")->name('lines' => \SOAP::Data->value(
                                                                                  SOAP::Data->type("ns:line")->name('line' => \SOAP::Data->value(
                                                                                            SOAP::Data->type("xsd:double")->name('qty')->value($HourWorked),
                                                                                            SOAP::Data->type("xsd:int")->name('product_id')->value(),
                                                                                            SOAP::Data->type("xsd:string")->name('product_ref')->value($BillingTypePerQueue->{$CurrentQueue})
                                                                                                    )
                                                                                            )
                                                                                  )
                                                                        )
                                                         )   
                                                );                                                      
      
my $som = $soap->call('updateInvoice',
                        $header,
                        $auth,
                        $invoice
);

#---- Erreur dans le retour du WS
if ( $som->fault ) {
  $RT::Logger->error ( "[Scrip Facturation#1] Error when calling webservice '".RT->Config->Get( 'DolibarrURL' )."/webservices/oeris_productorservice.php#getProductOrService' :" . $som->fault->{faultstring} );
} elsif ($som->result->{'result_code'} eq 'OK') {
  $RT::Logger->debug( "[Scrip Facturation#1] Mise a jour de la facture ".$ref_facture.". Temps travaillé = ".$HourWorked );
} else {
  $RT::Logger->error ( "[Scrip Facturation#1] Error when calling webservice '".RT->Config->Get( 'DolibarrURL' )."/webservices/oeris_productorservice.php#getProductOrService' :" . $som->result->{'result_label'} );
} 

```



### Dependences : ###


```
#!perl

            [p5-SOAP-Lite] (http://search.cpan.org/~phred/SOAP-Lite-1.20/lib/SOAP/Lite.pm) 
```
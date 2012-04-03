<?php

//================================================================
// Fedora Kickstart Generator Script
//================================================================
//  This script gathers information from an XML file,
//  and replaces keywords in a kickstart file.
//  These keywords include IP/repo/hostname info
//
//  Script coded for Fun, personal and professional reasons.
//  Code has not been copied, but many sources used:
//    http://phillipnb.wordpress.com/2011/05/03/php-and-xml-part-1/
//    http://ditio.net/2008/12/01/php-xpath-tutorial-advanced-xml-part-1/
//    http://php.net/manual/en/
//================================================================
// Version: 1.0       07/08/2011       Paul "1" Sanders
//              Initial code, works
//
// Version: 1.1       08/08/2011       Paul "1" Sanders
//              Addition of dateSTR + timezone
//              Addition of <active/> element checks
//              After install, XML file is changed and written out.
//              Added log dir + file, config dir
//
// Version: 1.2       28/08/2011       Paul "1" Sanders
//              
// Version: 1.3       27/09/2011       Paul "1" Sanders
//              windows.ks and some bug fixes
//
//================================================================


//================================================================
// DEFAULT VARIABLE DECLARATIONS
//================================================================

// Some default variable creations
date_default_timezone_set("Europe/London");
$dateSTR   = date("Ymd_Hi");
$configDIR = "./";
$logNAME   = $configDIR."log/build.".date("Ymd").".log";
$configXML = "servers.xml";
$configKS  = "new_build.ks";
$ethdev    = array() ;
$macdev    = array() ;
$values    = array();
$matches   = array();
$ethindex  = 0;

// Function for log output:
function log_out ($messageSTR) {
  global $logNAME;
  $logFILE = fopen($logNAME, "a");
  $dateST  = date("Y/m/d H:i:s");
  @fwrite($logFILE, $dateST.", ".$_SERVER['REMOTE_ADDR'].", ".$messageSTR."\n");
  fclose($logFILE);
}

// log out start info:
log_out("-------------Starting script on request-------------");

// Load XML file
$kickS = new DOMDocument();
$kickS->load($configXML);
$xkickS  = new DOMXPath($kickS);

// Default server config:
// Get pointer to specific <server> = "default"
// Grab XML for specific <server> and store it as text
// Convert text into XML DOM object
$DEFAULT    = $xkickS->query("/root/server[@name='default']");
$defaultSTR = $kickS->saveXML($DEFAULT->item(0));
$defaultXML = new SimpleXMLElement($defaultSTR);

// These are the keywords that need to be
// replaced within the kickstart file
// Add to the array to add a field which needs
// to be replaced
$matches[0] = '_YUMREPO_IP_';
$matches[1] = '_YUMREPO_OPTION_';
$matches[2] = '_SELINUX_OPTION_';
$matches[3] = '_TYPE_BOOT_';
$matches[4] = '_TYPE_DISK_';
$matches[5] = '_HOSTNAME_OPTION_';
$matches[6] = '_GATEWAY_OPTION_';
$matches[7] = '_PACKAGE_LIST_';
$matches[8] = '_NETWORK_LOOP_';
$matches[9] = '_SERVICES_LOOP_';


log_out("loaded default section - continuing");
//================================================================
// FINISHED DEFAULT STUFF
// NOW COMES HARDER STUFF
//================================================================


//================================================================
// FIND SERVER IN XML FILE
//================================================================

// Go through environment vars and look for a specific HTTP request
// These requests are made by the RedHat anaconda boot program
// They send an ethernet and MAC address
log_out("processing HTTP headers:");
foreach($_SERVER as $key => $value){
  if ( strpos($key, "HTTP_X_RHN_PROVISIONING_MAC") !== false) {
    $ethdev[$ethindex] = trim(strstr($value, " ", true));   // before space
    $macdev[$ethindex] = trim(strstr($value, " ", false));  // after space
    log_out("mac device: ".$key."-".$value.":".$ethdev[$ethindex]." -> ".$macdev[$ethindex]);
    $ethindex++;
  }
}

//================================================================
// TESTING
// ********** REMOVE ME LATER ***************

// $ethdev[0] = "eth0";
// $macdev[0] = "00:23:36:8A:0B:2D";
// $ethdev[1] = "eth1";
// $macdev[1] = "00:23:36:8A:0B:2F";
// $ethdev[2] = "eth2";
// $macdev[2] = "00:99:99:8A:0B:2F";
// $ethindex = 3;
// log_out("*******test mac's loaded*******");

//================================================================


// check each MAC in header until a match is found
foreach ($macdev as $MACADDR) {
  $MACname   = $xkickS->query("/root/server/mac[addr='".$MACADDR."']/..");
  if ($MACname->length == 1) {
    log_out("MAC '".$MACADDR."' found in XML file");
    break;
  }
}

// MACname should contain 1 pointer to the correct <server> tag
// Must be 1 pointer - or else chaos!
if ($MACname->length != 1) {
  log_out("ERROR: No matching MACs found!");
  return 13;
};

// Grab XML for specific <server> and store it as text
// Convert text into XML DOM object
$serverSTR = $kickS->saveXML($MACname->item(0));
$serverXML = new SimpleXMLElement($serverSTR);

// Check if server has the <active/> element (ie, we want to install)
if (!$serverXML->active) {
  log_out("ERROR: Server '".$serverXML->attributes()->name."' is not active!");
  return 22;
}

//================================================================
// POPULATE ARRAY WITH SERVER DETAILS
//================================================================

// This array holds details for the specific server
// we are building. These values get placed into the
// kickstart file using a string replace function

@$values[0] = $_SERVER['SERVER_ADDR'] ? $_SERVER['SERVER_ADDR'] : "1.2.3.4";
$values[1] = (string) $serverXML->oslevel;
$values[2] = "--".(string) $serverXML->selinux;
$values[3] = !strcasecmp((string) $serverXML->type, "virtual") ? "elevator=noop" : (string) $serverXML->boot;
$values[4] = !strcasecmp((string) $serverXML->type, "virtual") ? "hda" : "sda";
$values[5] = (string) $serverXML->attributes()->name;
$values[6] = (string) $serverXML->gateway;
log_out("Server details from config:\n".print_r($values, true));

$values[7] = str_replace(" ", "", trim((string) $defaultXML->pkgs)."\n".trim((string) $serverXML->pkgs));
$values[8] = "";        // eth devices  generated by loop  below
$values[9] = "";        // service list generated by loops below

//================================================================
// ETHERNET DEVICES LOOP
//================================================================
foreach ($serverXML->mac as $macdevice) {
// Only create info on devices in config file

  $ethdevice = "";
  for ($loopIT = 0; $loopIT < $ethindex; $loopIT++) {
    if ($macdev[$loopIT] == $macdevice->addr) {
      $ethdevice = $ethdev[$loopIT];
    }
  }
  
  $ethparams = $macdevice->params ? (string) $macdevice->params : (string) $defaultXML->mac->params;
  $networkSTR = "network --bootproto=static --onboot=yes --noipv6 --device=".$ethdevice." --ip=".$macdevice->IP." --netmask=".$macdevice->netmask." --ethtool=\"".$ethparams."\"";
// 'network --bootproto=static --onboot=yes --noipv6 --device=eth0 --ip=1.2.3.4 --netmask=255.255.255.0 --ethtool="speed auto"';
  $values[8] = $networkSTR."\n".$values[8];
}

//================================================================
// SERVICES ON / OFF LOOPS
//================================================================
$SVClist = str_replace(" ", "", trim($defaultXML->services->on));
foreach (explode("\n", $SVClist) as $SVCname) {
  $values[9] = $values[9]."chkconfig --level 123 ".(string) $SVCname." on\n";
}
$SVClist = str_replace(" ", "", trim($defaultXML->services->off));
foreach (explode("\n", $SVClist) as $SVCname) {
  $values[9] = $values[9]."chkconfig --level 0123456 ".(string) $SVCname." off\n";
}
$SVClist = str_replace(" ", "", trim($serverXML->services->on));
foreach (explode("\n", $SVClist) as $SVCname) {
  $values[9] = $values[9]."chkconfig --level 123 ".(string) $SVCname." on\n";
}
$SVClist = str_replace(" ", "", trim($serverXML->services->off));
foreach (explode("\n", $SVClist) as $SVCname) {
  $values[9] = $values[9]."chkconfig --level 0123456 ".(string) $SVCname." off\n";
}

log_out("Finished gathering config - outputting kickstart file");


//================================================================
// LOAD KICKSTART (update/new build)
//================================================================

$configKS = $serverXML->update ? "update.ks" : $configKS;
$configKS = $serverXML->windows ? "windows.ks" : $configKS;

// Load kickstart file
$DATA = file_get_contents($configKS);


//================================================================
// REPLACE KEYWORDS WITH VALUES
//================================================================

print(str_replace($matches, $values, $DATA));


//================================================================
// BACKUP XML CONFIG - WRITE NEW ONE OUT
//================================================================
// The below is now done as part of the sync action in the kickstart file.

// log_out("removing <active/> status from xml file");

// save $kickS -> $configDIR."old/".$configXML.".".$dateSTR
// $kickS->save($configDIR."old/".$configXML.".".$dateSTR);

// remove <active/> element
// $pointer = $xkickS->query("/root/server[@name='".$serverXML->attributes()->name."']/active");
// $domref  = dom_import_simplexml($pointer->item(0));
// $domref->parentNode->removeChild($domref);

// save $kickS -> $configXML
// $kickS->save($configXML);

// log_out("File saved.");

?>

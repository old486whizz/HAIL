<?php

//================================================================
// System Kickstart Generator Script
//================================================================
//  Script created to load in SSH key
//  Then to remove <active> flag when required
//
//  Must have sudo config to allow:
//     passwd -[ul] initial_sync
//     cp /tmp/[a-zA-Z0-9]* ~initial_sync/.ssh/authorized_keys
//
//================================================================
// Version: 1.0       29/08/2011       Paul "1" Sanders
//              Initial code, works
//
//================================================================

//================================================================
// DEFAULT VARIABLE DECLARATIONS
//================================================================

// Some default variable creations
date_default_timezone_set("Europe/London");
$dateSTR   = date("Ymd_Hi");
$configDIR = "./";
$logNAME   = $configDIR."log/SYNC.".date("Ymd").".log";
$configXML = "servers.xml";

// Function for log output:
function log_out ($messageSTR) {
  global $logNAME;
  $logFILE = fopen($logNAME, "a");
  $dateST  = date("Y/m/d H:i:s");
  @fwrite($logFILE, $dateST.", ".$_SERVER['REMOTE_ADDR'].", ".$messageSTR."\n");
  fclose($logFILE);
}

function check_host ($HOSTNAME) {
  global $kickS;
  global $xkickS;
  $pointer = $xkickS->query("/root/server[@name='".$HOSTNAME."']");

  $serverSTR = $kickS->saveXML($pointer->item(0));
  $serverXML = new SimpleXMLElement($serverSTR);

  $strreturn = $serverXML->active ? true : false ;

  return $strreturn;
}

//================================================================
// MAIN SCRIPT
//================================================================

// Load XML file
$kickS = new DOMDocument();
$kickS->load($configXML);
$xkickS  = new DOMXPath($kickS);

// $_POST['host'] = "testsv001";

log_out("-------------Starting script on request-------------");
if (!check_host($_POST['host'])) {
  // You aren't valid! Let's exit..
  log_out("page request does not contain valid host. POST data:\n".print_r($_POST, true));
  return 11;
} else {
  log_out("host '".$_POST['host']."' is <active>.");
}

if ($_FILES['key']) {
  // file uploaded - check the hostname is <active>
  log_out("File uploaded (name: '".$_FILES['key']['name']."' host: '".$_POST['host']."')");

  // place file into users' authorized_keys area
  log_out("placing file into authorized_keys file");
  system("sudo cp ".$_FILES['key']['tmp_name']." ~initial_sync/.ssh/authorized_keys");
  log_out("Unlocking account.");

  system("sudo passwd -u initial_sync");
  log_out("waiting for lock request");

} elseif ($_POST['host']) {
  // host has been given - remove active flag + lock account
  log_out("lock request has been sent - locking account");
  system("sudo passwd -l initial_sync");

  // save $kickS -> $configDIR."old/".$configXML.".".$dateSTR
  $kickS->save($configDIR."old/".$configXML.".".$dateSTR);

  // remove <active/> element
  $pointer = $xkickS->query("/root/server[@name='".$_POST['host']."']/active");
  $domref  = dom_import_simplexml($pointer->item(0));
  $domref->parentNode->removeChild($domref);

  // save $kickS -> $configXML
  $kickS->save($configXML);
}

?>

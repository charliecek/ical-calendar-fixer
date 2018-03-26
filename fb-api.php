<?php
// SILENCE! I KILL YOU! //
// exit;
function getEventIdFromUrl($strUrl) {
  $aUrlParts = explode("/", $strUrl);
  return $aUrlParts[count($aUrlParts)-2];
}
function getEventCover($strEventID = '', $bDebug = false) {
  $aReturn = array(
  );
  
  if (empty($strEventID)) {
    $aReturn["success"] = false;
    $aReturn["message"] = "No event ID!";
    return $aReturn;
  }
  try{
    require_once( dirname(__FILE__) . '/fb-api-sdk/autoload.php' );
  }
  catch(Exception $o){
    $aReturn["success"] = false;
    $aReturn["exception"] = $o;
    return $aReturn;
  }

  $aConfig = array(
    'app_id' => '768253436664320',
    'app_secret' => 'd56732792055592c7eaa87a3838affdf',
    'default_graph_version' => 'v2.11'
  );
  $sAccessToken = 'EAAK6uPEwmgABAFyLA8zMOZCRvjeXH5lRS5smmTDJoV0DhBe6xfF7PXbTewSPynMek8gMX3DMAHCry8FRIgtrmeL9xFOnDwRug27kDE6JTwsVUbVEZBAOWPUAbPcpZAl3r03ZAOzZCtkyOlkzjSkUPjFkOGNwSyNwZD';

  $objFacebook = new Facebook\Facebook($aConfig);

  try {
//     $objFacebook->setDefaultAccessToken($aConfig['app_id'].'|'.$aConfig['app_secret']);
//     $objFacebook->setDefaultAccessToken('EAAK6uPEwmgABAFyLA8zMOZCRvjeXH5lRS5smmTDJoV0DhBe6xfF7PXbTewSPynMek8gMX3DMAHCry8FRIgtrmeL9xFOnDwRug27kDE6JTwsVUbVEZBAOWPUAbPcpZAl3r03ZAOzZCtkyOlkzjSkUPjFkOGNwSyNwZD');
//     var_dump($objFacebook->get("/".$strEventID));
    $objResponse = $objFacebook->get("/".$strEventID.'?fields=id,cover', $sAccessToken );
//     $objResponse = $objFacebook->get("/".$strEventID, $sAccessToken );
//     $objResponse = $objFacebook->get($strEventID.'?fields=cover');
  //   $objResponse = $objFacebook->sendRequest('GET', $strEventID, ['fields' => 'cover']);
  } catch(\Facebook\Exceptions\FacebookResponseException $e) {
    // When Graph returns an error
    $aReturn["success"] = false;
    $aReturn["message"] = 'Graph returned an error: ' . $e->getMessage();
    return $aReturn;
  } catch(\Facebook\Exceptions\FacebookSDKException $e) {
    // When validation fails or other local issues
    $aReturn["success"] = false;
    $aReturn["message"] = 'Facebook SDK returned an error: ' . $e->getMessage();
    return $aReturn;
  }

  var_dump($objResponse->getGraphNode());
  $aDecodedBody = $objResponse->getDecodedBody();
  var_dump($aDecodedBody);
  $strCoverPhotoSource = $aDecodedBody["cover"]["source"];

  if (isset($aDecodedBody) && !empty($aDecodedBody)) {
    if (isset($aDecodedBody["cover"]) && !empty($aDecodedBody["cover"])) {
      $aReturn["success"] = true;
      $aReturn["cover"] = $aDecodedBody["cover"];
      return $aReturn;
    } else {
      $aReturn["success"] = false;
      $aReturn["message"] = "No cover in decoded body!";
      return $aReturn;
    }
  } else {
    $aReturn["success"] = false;
    $aReturn["message"] = "No decoded body!";
    return $aReturn;
  }
}
$aEventIDs = array(
  "https://www.facebook.com/events/544464292557832/",
//   "https://www.facebook.com/events/1479547652100502/",
//   "https://www.facebook.com/events/1775232659444500/",
//   "https://www.facebook.com/events/803233969884025/"
);
echo "<pre>";

foreach ($aEventIDs as $strEventID) {
  var_dump($strEventID);
  var_dump(getEventCover($strEventID));
  break;
}

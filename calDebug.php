<?php

$strUrl = "";
$bStart = false;
$bDownload = true;
foreach ($_GET as $key => $val) {
  if ($key === "calUrl") {
    $bStart = true;
    $strUrl = $val;
  } else if ($key == "download") {
    $bDownload = ($val == 1 || $val == "1");
    continue;
  } else if ($bStart) {
    $strUrl .= "&" . $key . "=" . $val;
  }
}

if (empty($strUrl)) {
  $strUrl = 'https://www.facebook.com/ical/u.php?uid=100013643946992&key=AQD0Yq-CxBj1axe5';
}

$aHeader = array();
$strHeader = "";
$aDayCounts = array();
global $aHeader, $strHeader, $aDayCounts;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $strUrl);
curl_setopt($ch, CURLOPT_FAILONERROR, true); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");
curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
$response = curl_exec($ch);
curl_close($ch);

// parse contact name and email from organizer //
$string = $response;
$pattern = '/('.preg_quote('ORGANIZER;CN=').')([^:]*)'.preg_quote(':MAILTO:').'(.*)/';
$replacement = '$1$2:MAILTO:$3'."\n".'CONTACT:$2;$3';
$response = preg_replace($pattern, $replacement, $string);

// add cover photo //
$response = addCoverPhotos($response);

if ($bDownload) {
  header($aHeader["Content-Type"]);
  header($aHeader["Content-Disposition"]);
  echo $response;
} else {
  echo "<pre>";
  echo $response;
  echo "</pre>";
}

function HandleHeaderLine( $curl, $strHeaderLine ) {
    global $aHeader, $strHeader;
    $aHeaderLine = explode(":", $strHeaderLine);
    if (isset($aHeaderLine[1])) {
      $aHeader[$aHeaderLine[0]] = trim($strHeaderLine);
    }
    $strHeader .= trim($strHeaderLine)."\n";
    return strlen($strHeaderLine);
}
function getCoverPhoto($strBody) {
  $aMatches = array();
  preg_match('/<img[^>]*src=("[^">]*")[^>]*class="[^"]*coverPhotoImg/', $strBody, $aMatches);
  if (empty($aMatches)) {
    preg_match('/<img[^>]*class="[^"]*coverPhotoImg[^"]*"[^>]*src=("[^">]*")/', $strBody, $aMatches);
  }
  return trim($aMatches[1], '"\'');
}
function addCoverPhotos($strResponse) {
  $aMatches = array();
  preg_match_all( '/\n'.preg_quote('URL:').'.*\n/i', $strResponse, $aMatches );
  $bChange = false;
  $aCachedPhotos = getCachedPhotos();

  foreach ($aMatches[0] as $strMatch) {
    $bNew = true;
    $strEventUrl = trim(str_ireplace("url:", "", $strMatch));
    $bNewChange = false;
    $strCoverPhoto = getCachedPhoto($aCachedPhotos, $strEventUrl, $bNew, $bNewChange);
    $bChange = $bChange || $bNewChange;
    // $strCoverPhoto = false;
    if (false === $strCoverPhoto) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $strEventUrl);
      curl_setopt($ch, CURLOPT_FAILONERROR, true); 
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
      $strMatchResponse = curl_exec($ch);
      curl_close($ch);
      $strResponse = addContactUrl($strResponse, $strEventUrl, $strMatchResponse);

      $strCoverPhoto = getCoverPhoto($strMatchResponse);
      if (!empty($strCoverPhoto)) {
        $aCachedPhotos = addPhotoToCache($aCachedPhotos, $strEventUrl, $strCoverPhoto, $bNew);
        $bChange = true;
      }
    } else {
      $strResponse = addContactUrl($strResponse, $strEventUrl);
    }

    if (!empty($strCoverPhoto)) {
      if ((isset($bNew) && $bNew) || !isset($bNew)) {
        $strNew = "1";
      } else {
        $strNew = "0";
      }
      $strResponse = str_replace(
        "URL:".$strEventUrl,
        "URL:".$strEventUrl . '_-_-_' . $strCoverPhoto . '_-_-_' . $strNew,
        $strResponse
      );
      // break;
    }
  }
  if ($bChange) {
    putCachedPhotos($aCachedPhotos);
  }
  return $strResponse;
}

function addContactUrl($strResponse, $strEventUrl, $strMatchResponse = false) {
  $strFileName = __DIR__ . "/cached_contact_urls.kk";
  if (file_exists($strFileName)) {
    $strCachedPhotos = file_get_contents($strFileName);
    eval('$aCachedContactUrls = ' . $strCachedPhotos . ';');
  } else {
    $aCachedContactUrls = array();
  }
  if ($strMatchResponse === false) {
    // there is no strMatchResponse - we don't have a scraped page //
    if (isset($aCachedContactUrls[$strEventUrl])) {
      return replaceContactUrl($strResponse, $strEventUrl, $aCachedContactUrls[$strEventUrl]);
    } else {
      return $strResponse;
    }
  } else {
    $aEvent = getEventByEventUrl($strResponse, $strEventUrl);
    if (!empty($aEvent) && isset($aEvent['CONTACT'])) {
      $aContact = explode( ";", $aEvent['CONTACT'] );
      $strContactName = $aContact[0];
      $strContactUrl = parseContactUrlByName($strMatchResponse, $strContactName);
      if (!empty($strContactUrl)) {
        $aCachedContactUrls[$strEventUrl] = $strContactUrl;
        file_put_contents($strFileName, var_export($aCachedContactUrls, true));
        return replaceContactUrl($strResponse, $strEventUrl, $strContactUrl, $aEvent);
      } else {
        return $strResponse;
      }
    } else {
      return $strResponse;
    }
  }
}
function parseContactUrlByName($strMatchResponse, $strContactName) {
  $aMatches = array();
  $strPattern =
    preg_quote("<a", '/')
    .'[^>]*href="([^">]*)"[^>]*'
    .preg_quote(">", '/')
    .preg_quote($strContactName, '/')
    .preg_quote("</a>", '/');
  preg_match('/'.$strPattern.'/is', $strMatchResponse, $aMatches);
  if (isset($aMatches[1])) {
    return $aMatches[1];
  }
  return '';
}
function getEventByEventUrl($strResponse, $strEventUrl) {
  $aMatches = array();
  $strPattern =
    preg_quote("BEGIN:VEVENT")
    .'.*?'
    .preg_quote($strEventUrl, '/')
    .'.*?'
    .preg_quote("END:VEVENT");
  preg_match('/'.$strPattern.'/s', $strResponse, $aMatches);
  if (!empty($aMatches[0])) {
    $aEventReversed0 = array_reverse(explode("\n",$aMatches[0]));
    $aEventReversed = array();
    foreach ($aEventReversed0 as $strEventLine) {
      $aEventReversed[] = $strEventLine;
      if (trim($strEventLine) == "BEGIN:VEVENT") {
        break;
      }
    }
    $aEvent = array_reverse($aEventReversed);
    $aResult = array();
    foreach ($aEvent as $strLine) {
      $aLine = explode(":", $strLine);
      $aLine2 = $aLine;
      unset($aLine2[0]);
      $strLine = implode(":", $aLine2);
      $aResult[$aLine[0]] = $strLine;
    }
    return $aResult;
  }
  return array();
}
function replaceContactUrl($strResponse, $strEventUrl, $strContactUrl, $aEvent = array()) {
  if (empty($aEvent)) {
    $aEvent = getEventByEventUrl($strResponse, $strEventUrl);
  }
  $strContact = trim($aEvent['CONTACT']);
  $aContact = explode(";", $strContact);
  $strContact = $aContact[0].";".$aContact[1];
  $strPattern = '(' . preg_quote("CONTACT:".$strContact, '/') . ")([^;])";
  return preg_replace('/'.$strPattern.'/s', '$1;'.$strContactUrl.'$2', $strResponse);
  // return str_replace("CONTACT:".$strContact, "CONTACT:".$strContact.";".$strContactUrl, $strResponse);
}
function getCachedPhotos() {
  $strFileName = __DIR__ . "/cached_imgs.kk";
  if (file_exists($strFileName)) {
    $strCachedPhotos = file_get_contents($strFileName);
    eval('$aArr = ' . $strCachedPhotos . ';');
    if (is_array($aArr)) {
      setDayCounts($aArr);
      return $aArr;
    } else {
      return array();
    }
  } else {
    return array();
  }
}
function setDayCounts($aCachedPhotos) {
  global $aDayCounts;
  foreach ($aCachedPhotos as $aPhoto) {
    if (isset($aPhoto['day'])) {
      $iDayOfWeek = $aPhoto['day'];
      if (isset($aDayCounts[$iDayOfWeek])) {
        $aDayCounts[$iDayOfWeek]++;
      } else {
        $aDayCounts[$iDayOfWeek] = 1;
      }
    }
  }
}
function putCachedPhotos($aCachedPhotos) {
  $strFileName = __DIR__ . "/cached_imgs.kk";
  return file_put_contents($strFileName, var_export($aCachedPhotos, true));
}
function getCachedPhoto(&$aCachedPhotos, $strEventUrl, &$bNew, &$bChange) {
  $aPhoto = $aCachedPhotos[$strEventUrl];
  
  if (!isset($aPhoto) || empty($aPhoto)) {
    // photo is not cached yet //
    $bNew = true;
    $bChange = true;
    return false;
  } else {
    // photo is cached //
    $iDayOfWeek = date('N');
    if ($iDayOfWeek != $aPhoto['day'] && isset($aPhoto['url']) && !empty($aPhoto['url'])) {
      // not the day when the photo should be updated //
      $bNew = false;
      $mixReturn = $aPhoto['url'];
    } else {
      // photo should be updated or there is no url //
      $iTime = time();
      $iTimeout = 60*60*24;
      if ($iTime - intval($aPhoto['time']) > $iTimeout) {
        // photo timed out //
        $bNew = true;
        $mixReturn = false;
      } else if (isset($aPhoto['url']) && !empty($aPhoto['url'])) {
        // photo hasn't timed out yet and has a valid url //
        $bNew = false;
        $mixReturn = $aPhoto['url'];
      } else {
        // photo hasn't timed out yet but there is no valid url //
        $bNew = true;
        $mixReturn = false;
      }
    }
  }
  $bChange = (!isset($aPhoto['new']) || $aPhoto['new'] !== $bNew);
  if ($bChange) {
    $aCachedPhotos[$strEventUrl]['new'] = $bNew;
  }
  return $mixReturn;
}
function addPhotoToCache($aCachedPhotos, $strEventUrl, $strPhotoUrl, &$bNew) {
  $aPhoto = $aCachedPhotos[$strEventUrl];
  global $aDayCounts;
  $iDayOfWeek = min(array_keys($aDayCounts, min($aDayCounts)));
  $aDayCounts[$iDayOfWeek]++;
  $iTime = time();
  if (!isset($aPhoto) || empty($aPhoto)) {
    $aCachedPhotos[$strEventUrl] = array(
      'day' => $iDayOfWeek,
      'time' => $iTime,
      'url' => $strPhotoUrl,
      'new' => true
    );
  } else {
    if ($aCachedPhotos[$strEventUrl]['url'] == $strPhotoUrl) {
      $aCachedPhotos[$strEventUrl]['new'] = false;
    } else {
      $aCachedPhotos[$strEventUrl]['url'] = $strPhotoUrl;
      $aCachedPhotos[$strEventUrl]['new'] = true;
    }
    $aCachedPhotos[$strEventUrl]['time'] = $iTime;
  }
  if (!isset($aCachedPhotos[$strEventUrl]['new'])) {
    $aCachedPhotos[$strEventUrl]['new'] = true;
  }
  $bNew = $aCachedPhotos[$strEventUrl]['new'];

  return $aCachedPhotos;
}

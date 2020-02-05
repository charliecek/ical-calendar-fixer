<?php

$strUrl    = "";
$bStart    = false;
$bDownload = true;
foreach ( $_GET as $key => $val ) {
  if ( $key === "calUrl" ) {
    $bStart = true;
    $strUrl = $val;
  } else if ( $key == "download" ) {
    $bDownload = ( $val == 1 || $val == "1" );
    continue;
  } else if ( $bStart ) {
    $strUrl .= "&" . $key . "=" . $val;
  }
}

if ( empty( $strUrl ) ) {
  // $strUrl = 'https://www.facebook.com/ical/u.php?uid=100013643946992&key=AQD0Yq-CxBj1axe5'; // old srd
  $strUrl = 'https://www.facebook.com/events/ical/upcoming/?uid=100013643946992&key=rDcoUzEADZRAsURb'; // srd
  // $strUrl = 'https://www.facebook.com/events/ical/upcoming/?uid=1057854135&key=O9Y6HnaJZY2WKNFw'; // kajo
}

$strUrl = html_entity_decode( urldecode( $strUrl ) );

// var_dump($strUrl);
// var_dump('https://www.facebook.com/events/ical/upcoming/?uid=100013643946992&key=rDcoUzEADZRAsURb'); // srd
// var_dump('https://www.facebook.com/events/ical/upcoming/?uid=1057854135&key=O9Y6HnaJZY2WKNFw'); // kajo
// exit(0);

$aHeader    = array();
$strHeader  = "";
$aDayCounts = array();
global $aHeader, $strHeader, $aDayCounts;

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, $strUrl );
curl_setopt( $ch, CURLOPT_FAILONERROR, true );
curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt( $ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine" );
curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36' );
$response = curl_exec( $ch );
curl_close( $ch );

// parse contact name and email from organizer //
$string = $response;

// var_dump($response);
// exit(0);

$pattern     = '/(' . preg_quote( 'ORGANIZER;CN=' ) . ')([^:]*)' . preg_quote( ':MAILTO:' ) . '(.*)/';
$replacement = '$1$2:MAILTO:$3' . "\n" . 'CONTACT:$2;$3';
$response    = preg_replace( $pattern, $replacement, $string );

// add cover photo //
$response = addCoverPhotos( $response );

$strFileName = __DIR__ . "/latest.ical.txt";
file_put_contents( $strFileName, $response );

if ( $bDownload ) {
  header( $aHeader["Content-Type"] );
  header( $aHeader["Content-Disposition"] );
  echo $response;
} else {
  echo "<pre>";
  echo $response;
  echo "</pre>";
}

function HandleHeaderLine( $curl, $strHeaderLine ) {
  global $aHeader, $strHeader;
  $aHeaderLine = explode( ":", $strHeaderLine );
  if ( isset( $aHeaderLine[1] ) ) {
    $aHeader[ $aHeaderLine[0] ] = trim( $strHeaderLine );
  }
  $strHeader .= trim( $strHeaderLine ) . "\n";

  return strlen( $strHeaderLine );
}

function getCoverPhoto( $strBody ) {
  $aMatches = array();
  preg_match( '/<a[^>]*data-ploi=("[^">]*")[^>]*class="[^"]*(_fbEventsPermalinkHeader__coverPhotoLink)/', $strBody, $aMatches );
  if ( ! empty( $aMatches ) ) {
    return trim( $aMatches[1], '"\'' );
  }
  preg_match( '/<a[^>]*class="[^"]*(_fbEventsPermalinkHeader__coverPhotoLink)[^>]*data-ploi=("[^">]*")/', $strBody, $aMatches );
  if ( ! empty( $aMatches ) ) {
    return trim( $aMatches[2], '"\'' );
  }
  preg_match( '/<img[^>]*src=("[^">]*")[^>]*class="[^"]*(coverPhotoImg|scaledImageFitHeight|scaledImageFitWidth)/', $strBody, $aMatches );
  if ( ! empty( $aMatches ) ) {
    return trim( $aMatches[1], '"\'' );
  }
  preg_match( '/<img[^>]*class="[^"]*(coverPhotoImg|scaledImageFitHeight|scaledImageFitWidth)[^"]*"[^>]*src=("[^">]*")/', $strBody, $aMatches );
  if ( ! isset( $aMatches[2] ) ) {
    return "";
  }

  return trim( $aMatches[2], '"\'' );
}

function getEventIdFromUrl( $strUrl ) {
  $aUrlParts = explode( "/", $strUrl );

  return $aUrlParts[ count( $aUrlParts ) - 2 ];
}

function getEventCover( $strEventID = '' ) {
  $aReturn = array();

  if ( empty( $strEventID ) ) {
    $aReturn["success"] = false;
    $aReturn["message"] = "No event ID!";

    return $aReturn;
  }
  try {
    require_once( dirname( __FILE__ ) . '/fb-api-sdk/autoload.php' );
  } catch ( Exception $o ) {
    $aReturn["success"]   = false;
    $aReturn["exception"] = $o;

    return $aReturn;
  }

  $aConfig = array(
    'app_id'                => '768253436664320',
    'app_secret'            => 'd56732792055592c7eaa87a3838affdf',
    'default_graph_version' => 'v2.10'
  );

  try {
    $objFacebook = new Facebook\Facebook( $aConfig );
    $objFacebook->setDefaultAccessToken( $aConfig['app_id'] . '|' . $aConfig['app_secret'] );
    $objResponse = $objFacebook->get( $strEventID . '?fields=cover' );
    //   $objResponse = $objFacebook->sendRequest('GET', $strEventID, ['fields' => 'cover']);
  } catch ( \Facebook\Exceptions\FacebookSDKException $e ) {
    // When validation fails or other local issues
    $aReturn["success"] = false;
    $aReturn["message"] = 'Facebook SDK returned an error: ' . $e->getMessage();

    return $aReturn;
  } catch ( Exception $e ) {
    $aReturn["success"] = false;
    $aReturn["message"] = 'Facebook returned an error: ' . $e->getMessage();

    return $aReturn;
  }

  $aDecodedBody = $objResponse->getDecodedBody();

  if ( isset( $aDecodedBody ) && ! empty( $aDecodedBody ) ) {
    if ( isset( $aDecodedBody["cover"] ) && ! empty( $aDecodedBody["cover"] ) ) {
      $aReturn["success"] = true;
      $aReturn["cover"]   = $aDecodedBody["cover"];

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

function addCoverPhotos( $strResponse ) {
  $aMatches = array();
  preg_match_all( '/\n' . preg_quote( 'URL:' ) . '.*\n/i', $strResponse, $aMatches );
  $bChange       = false;
  $aCachedPhotos = getCachedPhotos();

  foreach ( $aMatches[0] as $strMatch ) {
    $bNew          = true;
    $strEventUrl   = trim( str_ireplace( "url:", "", $strMatch ) );
    $bNewChange    = false;
    $strCoverPhoto = getCachedPhoto( $aCachedPhotos, $strEventUrl, $bNew, $bNewChange );
    $bChange       = $bChange || $bNewChange;
    // $strCoverPhoto = false;
    if ( false === $strCoverPhoto ) {
      // Make a request to get the organizer name (and if fb api fails, the cover photo) //
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_URL, $strEventUrl );
      curl_setopt( $ch, CURLOPT_FAILONERROR, true );
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
      curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13' );
      $strMatchResponse = curl_exec( $ch );
      curl_close( $ch );
      $strResponse = addContactUrl( $strResponse, $strEventUrl, $strMatchResponse );

      // Let's try the DB api first //
      $strEventID = getEventIdFromUrl( $strEventUrl );
      if ( isset( $strEventUrl ) && ! empty( $strEventUrl ) ) {
        $aResponse = getEventCover( $strEventID );
      }
      if ( isset( $aResponse ) && ! empty( $aResponse ) && $aResponse['success'] ) {
        // Success //
        $strCoverPhoto = $aResponse["cover"]["source"];
      }
      // One more check //
      if ( isset( $strCoverPhoto ) && ! empty( $strCoverPhoto ) ) {
        // Success => add the newly obtained photo //
        $aCachedPhotos = addPhotoToCache( $aCachedPhotos, $strEventUrl, $strCoverPhoto, $bNew );
        $bChange       = true;
      } else {
        // No cover photo obtained :(  => let's try to do it the old way by parsing it from the response //
        $strCoverPhoto = getCoverPhoto( $strMatchResponse );
        if ( ! empty( $strCoverPhoto ) ) {
          $aCachedPhotos = addPhotoToCache( $aCachedPhotos, $strEventUrl, $strCoverPhoto, $bNew );
          $bChange       = true;
        }
      }
    } else {
      // The cover photo is cached already //
      $strResponse = addContactUrl( $strResponse, $strEventUrl );
    }

    if ( ! empty( $strCoverPhoto ) ) {
      if ( ( isset( $bNew ) && $bNew ) || ! isset( $bNew ) ) {
        $strNew = "1";
      } else {
        $strNew = "0";
      }
      $strResponse = str_replace(
        "URL:" . $strEventUrl,
        "URL:" . $strEventUrl . '_-_-_' . $strCoverPhoto . '_-_-_' . $strNew,
        $strResponse
      );
      // break;
    }
  }
  if ( $bChange ) {
    putCachedPhotos( $aCachedPhotos );
  }

  return $strResponse;
}

function addContactUrl( $strResponse, $strEventUrl, $strMatchResponse = false ) {
  $strFileName = __DIR__ . "/cached_contact_urls.kk";
  if ( file_exists( $strFileName ) ) {
    $strCachedPhotos = file_get_contents( $strFileName );
    eval( '$aCachedContactUrls = ' . $strCachedPhotos . ';' );
  } else {
    $aCachedContactUrls = array();
  }
  if ( $strMatchResponse === false ) {
    // there is no strMatchResponse - we don't have a scraped page //
    if ( isset( $aCachedContactUrls[ $strEventUrl ] ) ) {
      return replaceContactUrl( $strResponse, $strEventUrl, $aCachedContactUrls[ $strEventUrl ] );
    } else {
      return $strResponse;
    }
  } else {
    $aEvent = getEventByEventUrl( $strResponse, $strEventUrl );
    if ( ! empty( $aEvent ) && isset( $aEvent['CONTACT'] ) ) {
      $aContact       = explode( ";", $aEvent['CONTACT'] );
      $strContactName = $aContact[0];
      $bDebug         = false;
      //       $bDebug = ($strEventUrl == 'https://www.facebook.com/events/996724657122336/');
      $strContactUrl = parseContactUrlByName( $strMatchResponse, $strContactName, $bDebug );
      if ( ! empty( $strContactUrl ) ) {
        $aCachedContactUrls[ $strEventUrl ] = $strContactUrl;
        file_put_contents( $strFileName, var_export( $aCachedContactUrls, true ) );

        return replaceContactUrl( $strResponse, $strEventUrl, $strContactUrl, $aEvent );
      } else {
        return $strResponse;
      }
    } else {
      return $strResponse;
    }
  }
}

function parseContactUrlByName( $strMatchResponse, $strContactName, $bDebug = false ) {
  $aMatches   = array();
  $strPattern =
    preg_quote( "<a", '/' )
    . '[^>]*href="(http[^">]*)"[^>]*'
    . preg_quote( ">", '/' )
    . preg_quote( $strContactName, '/' )
    . preg_quote( "</a>", '/' );
  preg_match( '/' . $strPattern . '/is', $strMatchResponse, $aMatches );
  if ( isset( $aMatches[1] ) ) {
    return $aMatches[1];
  }
  $strCleanPattern       = '~[^a-zA-Z0-9_/:\~-]~';
  $strMatchResponseClean = preg_replace( $strCleanPattern, '_', $strMatchResponse );
  $strContactNameClean   = preg_replace( $strCleanPattern, '_', $strContactName );
  if ( $bDebug ) {
    var_dump( $strContactName, $strContactNameClean, $strMatchResponseClean );
    exit;
  }
  $strPattern =
    preg_quote( "<a", '/' )
    . '[^>]*href="(http[^">]*)"[^>]*'
    . preg_quote( ">", '/' )
    . preg_quote( $strContactNameClean, '/' )
    . preg_quote( "</a>", '/' );
  preg_match( '/' . $strPattern . '/is', $strMatchResponseClean, $aMatches );
  if ( isset( $aMatches[1] ) ) {
    return $aMatches[1];
  }

  return '';
}

function getEventByEventUrl( $strResponse, $strEventUrl ) {
  $aMatches   = array();
  $strPattern =
    preg_quote( "BEGIN:VEVENT" )
    . '.*?'
    . preg_quote( $strEventUrl, '/' )
    . '.*?'
    . preg_quote( "END:VEVENT" );
  preg_match( '/' . $strPattern . '/s', $strResponse, $aMatches );
  if ( ! empty( $aMatches[0] ) ) {
    $aEventReversed0 = array_reverse( explode( "\n", $aMatches[0] ) );
    $aEventReversed  = array();
    foreach ( $aEventReversed0 as $strEventLine ) {
      $aEventReversed[] = $strEventLine;
      if ( trim( $strEventLine ) == "BEGIN:VEVENT" ) {
        break;
      }
    }
    $aEvent  = array_reverse( $aEventReversed );
    $aResult = array();
    foreach ( $aEvent as $strLine ) {
      $aLine  = explode( ":", $strLine );
      $aLine2 = $aLine;
      unset( $aLine2[0] );
      $strLine              = implode( ":", $aLine2 );
      $aResult[ $aLine[0] ] = $strLine;
    }

    return $aResult;
  }

  return array();
}

function replaceContactUrl( $strResponse, $strEventUrl, $strContactUrl, $aEvent = array() ) {
  if ( empty( $aEvent ) ) {
    $aEvent = getEventByEventUrl( $strResponse, $strEventUrl );
  }
  $strContact = trim( $aEvent['CONTACT'] );
  $aContact   = explode( ";", $strContact );
  $strContact = $aContact[0] . ";" . $aContact[1];
  $strPattern = '(' . preg_quote( "CONTACT:" . $strContact, '/' ) . ")([^;])";

  return preg_replace( '/' . $strPattern . '/s', '$1;' . $strContactUrl . '$2', $strResponse );
  // return str_replace("CONTACT:".$strContact, "CONTACT:".$strContact.";".$strContactUrl, $strResponse);
}

function getCachedPhotos() {
  $strFileName = __DIR__ . "/cached_imgs.kk";
  if ( file_exists( $strFileName ) ) {
    $strCachedPhotos = file_get_contents( $strFileName );
    $aArr            = array();
    eval( '$aArr = ' . $strCachedPhotos . ';' );
    if ( is_array( $aArr ) ) {
      setDayCounts( $aArr );

      return $aArr;
    } else {
      return array();
    }
  } else {
    return array();
  }
}

function setDayCounts( $aCachedPhotos ) {
  global $aDayCounts;
  foreach ( $aCachedPhotos as $aPhoto ) {
    if ( isset( $aPhoto['day'] ) ) {
      $iDayOfWeek = $aPhoto['day'];
      if ( isset( $aDayCounts[ $iDayOfWeek ] ) ) {
        $aDayCounts[ $iDayOfWeek ] ++;
      } else {
        $aDayCounts[ $iDayOfWeek ] = 1;
      }
    }
  }
}

function putCachedPhotos( $aCachedPhotos ) {
  $strFileName = __DIR__ . "/cached_imgs.kk";

  return file_put_contents( $strFileName, var_export( $aCachedPhotos, true ) );
}

function getCachedPhoto( &$aCachedPhotos, $strEventUrl, &$bNew, &$bChange ) {
  if ( isset( $aCachedPhotos[ $strEventUrl ] ) ) {
    $aPhoto = $aCachedPhotos[ $strEventUrl ];
  }

  if ( ! isset( $aPhoto ) || empty( $aPhoto ) ) {
    // photo is not cached yet //
    $bNew    = true;
    $bChange = true;

    return false;
  } else {
    // photo is cached //
    $iDayOfWeek = date( 'N' );
    if ( $iDayOfWeek != $aPhoto['day'] && isset( $aPhoto['url'] ) && ! empty( $aPhoto['url'] ) ) {
      // not the day when the photo should be updated //
      $bNew      = false;
      $mixReturn = $aPhoto['url'];
    } else {
      // photo should be updated or there is no url //
      $iTime    = time();
      $iTimeout = 60 * 60 * 24;
      if ( $iTime - intval( $aPhoto['time'] ) > $iTimeout ) {
        // photo timed out //
        $bNew      = true;
        $mixReturn = false;
      } else if ( isset( $aPhoto['url'] ) && ! empty( $aPhoto['url'] ) ) {
        // photo hasn't timed out yet and has a valid url //
        $bNew      = false;
        $mixReturn = $aPhoto['url'];
      } else {
        // photo hasn't timed out yet but there is no valid url //
        $bNew      = true;
        $mixReturn = false;
      }
    }
  }
  $bChange = ( ! isset( $aPhoto['new'] ) || $aPhoto['new'] !== $bNew );
  if ( $bChange ) {
    $aCachedPhotos[ $strEventUrl ]['new'] = $bNew;
  }

  return $mixReturn;
}

function addPhotoToCache( $aCachedPhotos, $strEventUrl, $strPhotoUrl, &$bNew ) {
  if ( isset( $aCachedPhotos[ $strEventUrl ] ) ) {
    $aPhoto = $aCachedPhotos[ $strEventUrl ];
  }
  global $aDayCounts;
  $iDayOfWeek = min( array_keys( $aDayCounts, min( $aDayCounts ) ) );
  $aDayCounts[ $iDayOfWeek ] ++;
  $iTime = time();
  if ( ! isset( $aPhoto ) || empty( $aPhoto ) ) {
    $aCachedPhotos[ $strEventUrl ] = array(
      'day'  => $iDayOfWeek,
      'time' => $iTime,
      'url'  => $strPhotoUrl,
      'new'  => true
    );
  } else {
    if ( $aCachedPhotos[ $strEventUrl ]['url'] == $strPhotoUrl ) {
      $aCachedPhotos[ $strEventUrl ]['new'] = false;
    } else {
      $aCachedPhotos[ $strEventUrl ]['url'] = $strPhotoUrl;
      $aCachedPhotos[ $strEventUrl ]['new'] = true;
    }
    $aCachedPhotos[ $strEventUrl ]['time'] = $iTime;
  }
  if ( ! isset( $aCachedPhotos[ $strEventUrl ]['new'] ) ) {
    $aCachedPhotos[ $strEventUrl ]['new'] = true;
  }
  $bNew = $aCachedPhotos[ $strEventUrl ]['new'];

  return $aCachedPhotos;
}

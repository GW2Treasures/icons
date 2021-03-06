<?php
  if(!isset($_GET['icon_file_id']) || !isset($_GET['icon_file_signature']))
    die("needs 'icon_file_signature' and 'icon_file_id'");

  ini_set('display_errors','Off');
  error_reporting(E_ALL);

  $temp            = '/tmp/icons/';
  $iconsBig        = __DIR__ . '/cache/128/';
  $icons           = __DIR__ . '/cache/64/';
  $iconsSmall      = __DIR__ . '/cache/32/';
  $iconsSuperSmall = __DIR__ . '/cache/16/';

  if( !file_exists($iconsBig)        ) { mkdir( $iconsBig        ); }
  if( !file_exists($icons)           ) { mkdir( $icons           ); }
  if( !file_exists($iconsSmall)      ) { mkdir( $iconsSmall      ); }
  if( !file_exists($iconsSuperSmall) ) { mkdir( $iconsSuperSmall ); }

  $pngout   = '/bin/pngout';
  $pngcrush = '/usr/bin/pngcrush';
  $advpng   = '/usr/bin/advpng';

  function getOriginalIcon( $icon_file_signature, $icon_file_id ) {
    global $temp;
    $url = 'https://render.guildwars2.com/file/' . $icon_file_signature . '/' . $icon_file_id . '.png';
    $tempFileName = $temp . uniqid() . '.png';

    $remoteFile = fopen( $url, 'r' );

    if( $remoteFile === false || !is_resource( $remoteFile )) {
      die('Could not open remote file ' . $url );
    }

    if( file_put_contents( $tempFileName, $remoteFile ) === false ) {
      //print_r(error_get_last());
      die('Could not write temp file');
    }

    return $tempFileName;
  }

  function minimizeFile( $input, $output ) {
    global $temp;
    global $pngout;
    global $pngcrush;
    global $advpng;

    $configurations = array(
      $pngout . ' -f6 -c2 -s0 -b128 -k0 -y -q',
      $pngout . ' -f6 -s0 -b128 -k0 -y -q',
      $pngout . ' -s0 -b128 -k0 -y -q',
      $pngout . ' -s0 -k0 -y -q',
      $pngcrush . ' -brute -blacken -reduce -q'
    );

    $tempFileNameBase = $temp . uniqid();

    $smallestFile = '';
    $smallestFileSize = 0;

    foreach ($configurations as $i => $args) {
      $tempFileName = $tempFileNameBase . $i . '.png';
      $command = $args . ' "' . $input . '" "' . $tempFileName . '"';
      system( $command, $out );

      if( file_exists( $tempFileName )) {
        $size = filesize( $tempFileName );
        if( $smallestFileSize == 0 || $size < $smallestFileSize ) {
          if( $smallestFile != '' ) {
            unlink( $smallestFile );
          }
          $smallestFile = $tempFileName;
          $smallestFileSize = $size;
        } else {
          unlink( $tempFileName );
        }
      }
    }

    if( $smallestFile == '' ) {
      die( 'Minimizing file failed!' );
      return false;
    } 

    //try to compress it even further with advpng
    $advFileName = $tempFileNameBase . 'adv.png';
    copy( $smallestFile, $advFileName );
    system( $advpng . ' -z4 ' . $advFileName . ' > /dev/null' );

    if( filesize( $advFileName ) < $smallestFileSize) {
      unlink( $smallestFile );
      $smallestFile = $advFileName;
    } else {
      unlink( $advFileName );
    }

    rename( $smallestFile, $output );

    return true;
  }

  function resizeIcon( $file, $size ) {
    global $temp;
    $tempFileName = $temp . uniqid() . '.png';

    list( $width, $height ) = getimagesize( $file );

    $img = imagecreatefrompng( $file );
    $out = imagecreatetruecolor( $size, $size);
    imagealphablending( $out, false );
    imagesavealpha( $out, true );

    $transparent = imagecolorallocatealpha( $out, 255, 255, 255, 127 );
     imagefilledrectangle($out, 0, 0, $size, $size, $transparent );

    imagecopyresampled( $out, $img, 0, 0, 0, 0, $size, $size, min($width, $height), min($width, $height) );
    imagepng( $out, $tempFileName );
    return $tempFileName;
  }

  function createBigIcon( $file ) {
    global $temp;
    $tempFileName = $temp . uniqid() . '.png';

    list( $width, $height ) = getimagesize( $file );

    $img = imagecreatefrompng( $file );
    $out = imagecreatetruecolor( 128, 128 );

    imagealphablending( $out, false );
    imagesavealpha( $out, true );

    $transparent = imagecolorallocatealpha( $out, 255, 255, 255, 127 );
    imagefilledrectangle($out, 0, 0, 128, 128, $transparent );

    $x = 128 - ( $width / 2 );
    $y = 128 - ( $height / 2 );

    imagecopyresampled( $out, $img, $x, $y, 0, 0, $width, $height, $width, $height );
    imagepng( $out, $tempFileName );
    return $tempFileName;
  }

  function gmstrtotime($sgm) {
    $months = array(
      'Jan'=>1,
      'Feb'=>2,
      'Mar'=>3,
      'Apr'=>4,
      'May'=>5,
      'Jun'=>6,
      'Jul'=>7,
      'Aug'=>8,
      'Sep'=>9,
      'Oct'=>10,
      'Nov'=>11,
      'Dec'=>12
    );
    list($D, $d, $M, $Y, $H, $i, $s) = sscanf($sgm, "%3s, %2d %3s %4d %2d:%2d:%2d GMT");
    return gmmktime($H, $i, $s, $months[$M], $d, $Y);
  }

  function setCacheHeader( $file ) {
    $mtime = filemtime($file);

    $header = apache_request_headers();
    if(!isset($_GET['force']) && array_key_exists("If-Modified-Since", $header) && ($ifModSince = $header["If-Modified-Since"]) != "") {
      $ifmodsince = gmstrtotime($header["If-Modified-Since"]);
      if($ifmodsince >= $mtime) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 304);
        exit;
      }
    }

    $secondsToCache = 86400*365;
    header('Cache-control: max-age='.$secondsToCache.', public', false, 200);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 200);
    header('Expires: '.gmdate('D, d M Y H:i:s',  $mtime + $secondsToCache).' GMT', true, 200);
    header('Content-Type: image/png');
  }

  $icon_file_id = $_GET['icon_file_id'];
  $icon_file_signature = $_GET['icon_file_signature'];

  $filenameBig        = $iconsBig        . $icon_file_signature . '-' . $icon_file_id . '.png';
  $filename           = $icons           . $icon_file_signature . '-' . $icon_file_id . '.png';
  $filenameSmall      = $iconsSmall      . $icon_file_signature . '-' . $icon_file_id . '.png';
  $filenameSuperSmall = $iconsSuperSmall . $icon_file_signature . '-' . $icon_file_id . '.png';

  if( !isset( $_GET['big']) && ( isset( $_GET['force'] ) || !file_exists( $filename ) || !file_exists( $filenameSmall ) || !file_exists( $filenameSuperSmall ) )) {
    $original = getOriginalIcon( $icon_file_signature, $icon_file_id );

    $normalSized = resizeIcon( $original, 64 );
    $smallSized  = resizeIcon( $original, 32 );
    $superSmallSized  = resizeIcon( $original, 16 );

    minimizeFile( $normalSized,      $filename );
    minimizeFile( $smallSized,       $filenameSmall );
    minimizeFile( $superSmallSized,  $filenameSuperSmall );

    header('x-gw2t: 1');

    unlink( $original );
    unlink( $normalSized );
    unlink( $smallSized );
    unlink( $superSmallSized );
  } elseif( isset( $_GET['big'] ) && ( isset( $_GET['force'] ) || !file_exists( $filenameBig ) )) {
    $original = getOriginalIcon( $icon_file_signature, $icon_file_id );

    list( $width, $height ) = getimagesize( $original );

    if( $width >= 128 && $height >= 128 ) {
      $bigSized = resizeIcon( $original, 128 );
    } else {
      $bigSized = createBigIcon( $original );
    }

    minimizeFile( $bigSized, $filenameBig );
    header('x-gw2t: big');

    unlink( $original );
    unlink( $bigSized );
  }

  if( isset( $_GET['big'] )) {
    setCacheHeader( $filenameBig );
    header('x-icon-size: 128');
    echo file_get_contents( $filenameBig );
  } elseif( isset( $_GET['small'] )) {
    setCacheHeader( $filenameSmall );
    header('x-icon-size: 32');
    echo file_get_contents( $filenameSmall );
  } elseif( isset( $_GET['supersmall'] )) {
    setCacheHeader( $filenameSuperSmall );
    header('x-icon-size: 16');
    echo file_get_contents( $filenameSuperSmall );
  } else {
    setCacheHeader( $filename );
    header('x-icon-size: 64');
    echo file_get_contents( $filename );
  }

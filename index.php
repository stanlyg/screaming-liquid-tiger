<?php
/******************************************************************************
 * Screaming Liquid Tiger
 *
 * Minimalistic podcast feed generator script for audiobooks
 *
 * @author     Marcel Bischoff
 * @copyright  2015-2016 Marcel Bischoff
 * @license    http://opensource.org/licenses/MIT The MIT Licence
 * @version    0.4.0
 * @link       https://github.com/herrbischoff/screaming-liquid-tiger
 * @since      File available since Release 0.1.0
 *****************************************************************************/

/**
 * Check PHP version
 */
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) :
    die('You need PHP 5.4+. You have ' . PHP_VERSION . '.');
endif;

/**
 * Make sure that unicode characters in file names are not dropped.
 */
setlocale(LC_CTYPE, "C.UTF-8");

/******************************************************************************
 * Configuration Start
 *****************************************************************************/

if (!file_exists('./config.php')) :

    /**
     * Whether to check for mediainfo
     */
    $mediainfo_check = false;

    $media_base_path = '.';

    /**
     * Feed info
     *
     * Basic feed information.
     *
     * description: basically anything you want, may appear in feed reader
     * link: dummy or real URL
     * title: your feed title as it appears in the feed reader
     * image: main image for feed (optional)
     */
    $conf = array(
        'title'       => 'Podcast Title',
        'description' => 'Long description of Podcast',
        'image'       => 'cover.jpg',
        'language'    => 'en',#iso639, 2 character codes
        'category'    => 'Fiction',
        'subcategory' => '',
        'explicit'    => '', #'', 'true', 'false'
        'author'      => 'Screaming Liquid Tiger',
        'link'        => 'http://example.com/audiobook',
        'ownername'   => 'Screaming Liquid Tiger Network.', #Contact info for itunes
        'ownermail'   => 'screamingliquidtiger@example.com',
        'type'        => 'serial', #'','serial','episodic'
        'copyright'   => 'Text copyright © 2000 Tiger, Audio Copyright © 2000 Lion.', 
        'block'       => 'yes', #'', 'yes' to hide from itunes
        'complete'    => '' #'', 'yes'
    );

    /**
     * File extensions
     *
     * Extensions to use for feed item creation. Add your own extensions to be
     * included, the corresponding MIME types are generated automatically.
     */
    $exts = array(
        'flac' => 'audio/flac',
        'm4a'  => 'audio/mp4',
        'm4b'  => 'audio/mp4',
        'mp3'  => 'audio/mp3',
        'mp4'  => 'audio/mp4',
        'oga'  => 'audio/ogg',
        'ogg'  => 'audio/ogg'
    );

else :

    require_once('./config.php');

endif;

/******************************************************************************
 * Configuration End
 *****************************************************************************/

/**
 * Output correct content type
 */
header('Content-type: application/rss+xml; charset=utf-8');

/**
 * Calculate the etag and compare
 */
$etag_hash = hash_init("sha256");
if ($handle = opendir($media_base_path)) :
    while ($files[] = readdir($handle));
    sort($files);

    foreach ($files as $entry) :
        $entry_path = $media_base_path . "/" . $entry;
        if (array_key_exists(pathinfo($entry_path, PATHINFO_EXTENSION), $exts) && !preg_match('/^\./', $entry)) :
            $mtime = (string)filemtime($entry_path);
            hash_update($etag_hash, $mtime);
        endif;
    endforeach;
    closedir($handle);
endif;
$etag = hash_final($etag_hash);

if (trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) :
    header("HTTP/1.1 304 Not Modified");
    exit;
endif;

header("ETag: $etag");

/**
 * Get mediainfo path
 */
$mediainfo = '';
if ($mediainfo_check) :
    $mediainfo_global = trim(shell_exec('which mediainfo'));
    $mediainfo_static = file_exists('./mediainfo');
    if ($mediainfo_global) $mediainfo = $mediainfo_global;
    if ($mediainfo_static) $mediainfo = './mediainfo';
    if (!$mediainfo) :
        die("For automatic tag reading functionality, you need mediainfo. Either install it globally on your server or download the correct static binary for your system here:\n\nhttps://mediaarea.net/en/MediaInfo/Download\n\nPut it in the same folder as this script. If you download a static build, make sure to configure your web server to block access to the binary for visitors.\n\nIn case you don't want this functionality at all, set the \$mediainfo_check variable back to 'false'.");
    endif;
endif;

/**
 * Format date according to RFC 822
 */
$date_fmt = 'D, d M Y H:i:s T';

/**
 * Check for HTTPS
 */
$prot = (isset($_SERVER['HTTPS']) != "on") ? 'http://' : 'https://';

/**
 * Determine base URL
 */
$base_url = str_replace("index.php", "", $prot . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"]);

/**
 * Set feed image if present
 * */
if ($conf['image'] && file_exists($conf['image'])) :
    $castimg_url = $base_url . rawurlencode($conf['image']);
endif;

/**
 * Construct feed
 */
$xmlstr = '<?xml version="1.0" encoding="UTF-8"?><rss/>';
$rss = new SimpleXMLElement($xmlstr);
$rss->addAttribute('version', '2.0');
$rss->addAttribute('xmlns:xmlns:atom', 'http://www.w3.org/2005/Atom');
$rss->addAttribute('xmlns:xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
$channel = $rss->addChild('channel');
$channel->addChild('title', $conf['title']);
$channel->addChild('description', $conf['description']);
if (isset($castimg_url)) :
    $itunes_image = $channel->addChild('xmlns:itunes:image');
    $itunes_image->addAttribute('href', $castimg_url);
endif;
$channel->addChild('language',$conf['language']);
$itunes_category = $channel->addChild('xmlns:itunes:category');
$itunes_category->addAttribute('text',$conf['category']);
if (!empty($conf['subcategory'])) :
  $itunes_subcategory = $itunes_category->addChild('xmlns:itunes:category');
  $itunes_subcategory->addAttribute('text',$conf['subcategory']);
endif;
if (!empty($conf['explicit'])) :
  $channel->addChild('xmlns:itunes:explicit',$conf['explicit']);
endif;
$channel->addChild('xmlns:itunes:author',$conf['author']);
$channel->addChild('link', $conf['link']);
if (!empty($conf['ownername'])) :
  $itunes_owner = $channel->addChild('xmlns:itunes:owner');
  $itunes_owner->addChild('xmlns:itunes:name',$conf['ownername']);
  $itunes_owner->addChild('xmlns:itunes:email',$conf['ownermail']);
endif;
if (!empty($conf['type'])) :
  $channel->addChild('xmlns:itunes:type',$conf['type']);
endif;
if (!empty($conf['copyright'])) :
  $channel->addChild('copyright',$conf['copyright']);
endif;
if (!empty($conf['block'])) :
  $channel->addChild('block',$conf['block']);
endif;
if (!empty($conf['complete'])) :
  $channel->addChild('complete',$conf['complete']);
endif;
$channel->addChild('pubDate', date($date_fmt));
$channel->addChild('lastBuildDate', date($date_fmt));
$atomlink = $channel->addChild('atom:atom:link');
$atomlink->addAttribute('rel', 'self');
$atomlink->addAttribute('type', 'application/rss+xml');

/**
 * Open file handler for current directory
 */
if ($handle = opendir($media_base_path)) :

    /**
     * Start item generation loop
     */
    while (false !== ($entry = readdir($handle))) :
        $entry_path = $entry;
        if ($media_base_path != ".") :
            $entry_path = $media_base_path . '/' . $entry;
        endif;

        /**
         * Make sure file matches extensions from array
         */
        if (array_key_exists(pathinfo($entry_path, PATHINFO_EXTENSION), $exts) && !preg_match('/^\./', $entry)) :
            $p = pathinfo($entry_path);
            $filename = $p['filename'];
            $fileimg_path = escapeshellarg('./tmp/' . $filename . '.jpg');
            $fileimg_url = $base_url . 'tmp/' . rawurlencode($filename . '.jpg');

            /**
             * Get mediainfo output for current file
             */
            ob_start();
            $opt = ' --Inform=\'General;%Duration/String3%#####%Performer% — %Album%\' ';
            $cmd = $mediainfo . $opt  . escapeshellarg($entry) . ' 2>&1';
            passthru($cmd);
            $mediainfo_out = ob_get_contents();
            ob_end_clean();

            /**
             * Parse mediainfo output
             */
            if ($mediainfo) :
                preg_match('/(\d{2}:\d{2}:\d{2}.\d+)#####(.+)/', $mediainfo_out, $matches);
                $duration = $matches[1];
                $title = $matches[2];
            else :
                $title = $p['filename'];
            endif;

            /**
             * Contruct feed item
             */
            $entry_urlsafe_path = implode("/", array_map("rawurlencode", explode("/", $entry_path)));
            $item = $channel->addChild('item');
            $item->addChild('title', $title);
            $guid = $item->addChild('guid', $base_url . $entry_urlsafe_path);
            $guid->addAttribute('isPermalink', 'false');
            $enclosure = $item->addChild('enclosure');
            $enclosure->addAttribute('url', $base_url . $entry_urlsafe_path);
            $enclosure->addAttribute('length', filesize($entry_path));
            $enclosure->addAttribute('type', $exts[$p['extension']]);
            $item->addChild('pubDate', date($date_fmt, filemtime($entry_path)));
            if ($mediainfo) :
                $item->addChild('xmlns:itunes:duration', $duration);
            endif;

        endif;

    /**
     * End item generation loop
     */
    endwhile;

endif;

/**
 * Close file handler
 */
closedir($handle);

/**
 * Output feed
 */
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($rss->asXML());
echo $dom->saveXML();


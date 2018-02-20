<?php
$url = $_REQUEST['url'];

// make sure we have a valid URL and not file path
if (!preg_match("`https?\://`i", $url)) {
    die('Not a URL');
}

// make the HTTP request to the requested URL
$content = file_get_contents($url);

// parse all links and forms actions and redirect back to this script
#$content = preg_replace("/some-smart-regex-here/i", "$1 or $2 smart replaces", $content);

echo $content;
?>
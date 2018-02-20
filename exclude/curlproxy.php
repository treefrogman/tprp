<?php
$url = $_REQUEST['url'];

// make sure we have a valid URL and not file path
if (!preg_match("`https?\://`i", $url)) {
    die('Not a URL');
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
#curl_setopt($ch, CURLOPT_VERBOSE, true);
#curl_setopt($ch, CURLOPT_HEADER, true);

// make the HTTP request to the requested URL
$content = curl_exec($ch);
curl_close($ch);

// parse all links and forms actions and redirect back to this script
#$content = preg_replace("/some-smart-regex-here/i", "$1 or $2 smart replaces", $content);

echo $content;
?>
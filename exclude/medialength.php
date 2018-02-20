<?php
$url = $_REQUEST['url'];

// make sure we have a valid URL and not file path
if (!preg_match("`https?\://`i", $url)) {
    die('Not a URL');
}

	// http://stackoverflow.com/questions/1401131/easiest-way-to-grab-filesize-of-remote-file-in-php
	
	//open connection
	$ch = curl_init();
	
	//set the url, number of POST vars, POST data
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	
	//execute post
	$result = curl_exec($ch);
	//echo $result;

	//get file length
	$beginningChop = explode("Content-Length: ", $result);
	$endChop = explode("\n", $beginningChop[1]);
	$length = $endChop[0];
	//echo "getMediaLength($url)";

echo $length;
?>
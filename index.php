<?='<?xml version="1.0" encoding="UTF-8"?>';?>
<?
//<pre style="font-family:Courier;white-space:pre-wrap;text-indent:5em hanging;">
require_once 'vendor/autoload.php';

$howMany = isset($_GET["limit"]) ? $_GET['limit'] : 24;
$fetchAllOverride = isset($_GET["fetchAllOverride"]);
$refetchMediaURLs = isset($_GET["refetchMediaURLs"]) ? $_GET['refetchMediaURLs'] : false;

//include 'error_reporting.php';

use Google\Cloud\Datastore\DatastoreClient;

$datastore = new DatastoreClient(["projectId" => "turning-point-radio-podcast"]);

// DJ fetcher functions
function getGrid($lastMonth, $viewMore) {
	$date = new DateTime();
	$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	if ($lastMonth) {
		$date = getDateOfFirstOfPreviousMonth($date);
	} else {
		$date = getDateOfFirstOfMonth($date);
	}
	$dateOfFirstOfMonth = $date->format("m/d/Y");
	$viewMore = $viewMore ? "true" : "false";
	
	//http://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
	$url = 'http://www.davidjeremiah.org/site/radio_archives.aspx?bid=0&start=' . $dateOfFirstOfMonth . '&sort_col=air_date&sort_dir=desc&view_more=' . $viewMore . '&grid_id=archives';
	$fields_string = "action=get_grid";
	
	//open connection
	$ch = curl_init();
	
	//set the url, number of POST vars, POST data
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	
	//execute post
	$result = curl_exec($ch);
	//var_dump($result);
	return $result;
}
function getMediaURL($bid) {
	//http://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
	$srcurl = 'http://www.davidjeremiah.org/site/radio_player.aspx?id=' . $bid;

	//fetch radio page
	$result = file_get_contents($srcurl);

	// get lines
	$lines = explode("\n", $result);
	// get the 26th line
	$line = $lines[25];
	
	// get the URL
	$chopBeginning = substr($line, 52);
	$chopOnQuotes = explode('"', $chopBeginning);

	$url = $chopOnQuotes[0];

	return $url;
}
function getMediaLength($url) {
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
	
	//get file length
	$beginningChop = explode("Content-Length: ", $result);
	$endChop = explode("\n", $beginningChop[1]);
	$length = $endChop[0];

	return $length;
}
function getEpisodesFromDJ($lastMonth, $viewMore, $stopAtBid) {
	$htmlSnippet = str_replace("&", "&amp;", getGrid($lastMonth, $viewMore));
	$html = "<html><body>" . $htmlSnippet . "</body></html>";
	$dom = new DOMDocument;
	$dom->loadHTML($htmlSnippet);
	$xpath = new DOMXpath($dom);
	$rows = $xpath->query("/html/body/table/tr[@class='']");
	$length = $rows->length;
	$episodeList = array();
	for ($index = 0; $index < $length; $index++) {
		$row = $rows->item($index);
		$descMobileNode = $xpath->query("./td[@class='grid_cell title']/div", $row)->item(1);
		$bid = substr($descMobileNode->getAttribute("id"), 19);
		if ($bid == $stopAtBid) {
			break;
		}
		$episode = new stdClass();
		$episode->bid = $bid;
		$date = parseDateFromDJ(trim($xpath->query("./td[@class='grid_cell air_date']", $row)->item(0)->nodeValue));
		$episode->date = formatDateForPodcast($date);
		$episode->title = utf8_decode(trim($xpath->query("./td[@class='grid_cell title']/div", $row)->item(0)->nodeValue));
		$episode->description = utf8_decode(trim($descMobileNode->nodeValue));
		$episode->mediaURL = getMediaURL($episode->bid);
		$episode->mediaLength = getMediaLength($episode->mediaURL);
		addEpisodeToDatastore($episode, $bid);
		array_push($episodeList, $episode);
	}
	return $episodeList;
}

// Datastore fetcher functions
function getLatestEpisodeFromDatastore() {
	global $datastore;
	$query = $datastore->query()
		->kind('Episode')
		->order('__key__', 'DESCENDING')
		->limit(1);
	$result = $datastore->runQuery($query);
	$result->rewind();
	if ($result->valid()) {
		$episode = $result->current()->get();
		return (object)$episode;
	} else {
		return null;
	}
}
function getEpisodesFromDatastore($howMany = false) {
	global $datastore, $refetchMediaURLs;
	$query = $datastore->query()
		->kind('Episode')
		->order('datetime', 'DESCENDING');
	if ($howMany) {
		$query->limit($howMany);
	}
	$result = $datastore->runQuery($query);
	$episodeList = array();
	foreach ($result as $key=>$item) {
		$episode = (object)($item->get());
		unset($episode->datetime);
		if ($refetchMediaURLs && $key > $refetchMediaURLs ) {
			$episode = refetchMediaURL($episode, $episode->bid);
		}
		array_push($episodeList, $episode);
	}
	return $episodeList;
}
function refetchMediaURL($episode, $bid) {
	echo $bid."\r\n";
	$url = getMediaURL($bid);
	$episode->mediaURL = $url;
	$episode->mediaLength = getMediaLength($url);
	addEpisodeToDatastore($episode, $bid);
	return $episode;
}
function addEpisodeToDatastore($episode, $bid) {
	global $datastore;
	$episodeArray = (array)$episode;
	$key = $datastore->key('Episode', $bid);
	$entity = $datastore->entity($key, $episodeArray);
	$entity["datetime"] = new DateTime($episode->date);
	$entity->setExcludeFromIndexes([
		"bid",
		"date",
		"title",
		"description",
		"mediaURL",
		"mediaLength"
	]);
	$transaction = $datastore->transaction();
	$transaction->upsert($entity);
	$transaction->commit();
}

// Date helper functions
function getDateOfFirstOfMonth($date) {
	$date = clone $date;
	$date->setDate($date->format('Y'), $date->format('m'), 1);
	return $date;
}
function getDateOfFirstOfPreviousMonth($date) {
	$date = clone $date;
	$date->setDate($date->format('Y'), $date->format('m') - 1, 1);
	return $date;
}
function getDatePlusNDays($date, $days) {
	$date = clone $date;
	$date->setDate($date->format('Y'), $date->format('m'), $date->format('d') + $days);
	return $date;
}
function getTodayDate() {
	$date = new DateTime();
	$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	$date->setTime(0, 0);
	return $date;
}
function dateIsSunday($date) {
	$w = $date->format("w");
	return (bool)($w == "0");
}
function dateIsSaturday($date) {
	$w = $date->format("w");
	return (bool)($w == "6");
}
function formatDateForPodcast($date) {
	return $date->format("D, d M Y H:i:s T");
}
function parseDateFromDJ($dateString) {
	$tz = new DateTimeZone('America/Los_Angeles');
	$date = DateTime::createFromFormat("n/j/Y H:i:s", $dateString . "00:00:00", $tz);
	return $date;
}

// Main function
function getLatestEpisodes($howMany = false) {
	global $fetchAllOverride;
	$latestEpisode = getLatestEpisodeFromDatastore();
	if ($latestEpisode) {
		$today = getTodayDate();
		$sunday = dateIsSunday($today);
		$saturday = dateIsSaturday($today);
		$latestEpisodeDate = new DateTime($latestEpisode->date);
		$latestEpisodeBid = $latestEpisode->bid;
		$fetchAll = false;
	} else {
		$fetchAll = true;
		$latestEpisodeBid = 0;
	}
	if ($fetchAllOverride) {
		getEpisodesFromDJ(false, true, 0);
	} else if ($fetchAll || (getDatePlusNDays($latestEpisodeDate, 4 + (!$saturday)) < $today)) {
	// If way out of date (missing more than five days—six if today is not Saturday)...
		// getGrid() with showMore
		getEpisodesFromDJ(false, true, $latestEpisodeBid);
	} else if (getDatePlusNDays($latestEpisodeDate, (int)$sunday) < $today) {
	// If only slightly out of date (missing today's episode—yesterday's if today is Sunday)...
		// getGrid() without showMore
		getEpisodesFromDJ(false, false, $latestEpisodeBid);
	}
	return getEpisodesFromDatastore($howMany);
}

$pubDate = formatDateForPodcast(getTodayDate());
$episodeList = getLatestEpisodes($howMany);
//</pre>
?>

<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0">
	<channel>
		<title>Turning Point Radio</title>
		<description>Turning Point is the broadcast ministry of Dr. David Jeremiah</description>
		<link>http://www.davidjeremiah.org/radio</link>
		<language>en-us</language>
		<copyright>Copyright 2000 - 2017 Dr. David Jeremiah</copyright>
		<lastBuildDate><?=$pubDate;?></lastBuildDate>
		<pubDate><?=$pubDate;?></pubDate>
		<docs>http://blogs.law.harvard.edu/tech/rss</docs>
		<webMaster>treefrogman@gmail.com</webMaster>
		<itunes:author>Dr. David Jeremiah</itunes:author>
		<itunes:subtitle>Turning Point is the broadcast ministry of Dr. David Jeremiah</itunes:subtitle>
		<itunes:summary>Turning Point is the broadcast ministry of Dr. David Jeremiah</itunes:summary>
		<itunes:owner>
			<itunes:name>Dr. David Jeremiah</itunes:name>
			<itunes:email>info@davidjeremiah.org</itunes:email>
		</itunes:owner>
		<itunes:explicit>No</itunes:explicit>
		<itunes:image href="http://turning-point-radio-podcast.appspot.com/Turning-Point-Cover-1400x1400.png"></itunes:image>
		<itunes:category text="News &amp; Politics">
			<itunes:category text="Religion &amp; Spirituality">
				 <itunes:category text="Christianity"></itunes:category>
			</itunes:category>
		</itunes:category>
<?

foreach ($episodeList as $episode) {

?>
		<item>
			<title><?=$episode->title;?></title>
			<link>http://www.davidjeremiah.org/site/radio_archives.aspx?bid=<?=$episode->bid;?></link>
			<guid><?=$episode->mediaURL;?></guid>
			<description><?=$episode->description;?></description>
			<enclosure url="<?=$episode->mediaURL;?>" length="<?=$episode->mediaLength;?>" type="audio/mpeg"/>
			<category>Podcasts</category>
			<pubDate><?=$episode->date;?></pubDate>
			<itunes:author>Dr. David Jeremiah</itunes:author>
			<itunes:explicit>No</itunes:explicit>
			<itunes:subtitle><?=$episode->description;?></itunes:subtitle>
			<itunes:summary><?=$episode->description;?></itunes:summary>
			<itunes:keywords>Turning Point, David Jeremiah, Sermons, Ministry</itunes:keywords>
		</item>
<?

}

?>
	</channel>
</rss>
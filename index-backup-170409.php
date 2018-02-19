<?='<?xml version="1.0" encoding="UTF-8"?>';?>
<?

require_once 'vendor/autoload.php';

//include 'error_reporting.php';

//$howManyEpisodes = (isset($_GET['howManyEpisodes'])) ? (int)$_GET['howManyEpisodes'] : 10;
$clearCache = isset($_GET['clearCache']);

$cutOffDate = DateTime::createFromFormat("n/j/Y", "2/1/2016"); // bid 1905

use Google\Cloud\Datastore\DatastoreClient;
//use Google\Cloud\Datastore\Query\Query;
$datastore = new DatastoreClient(["projectId" => "turning-point-radio-podcast"]);
//var_dump($datastore);
/*



*/

function getLatestEpisodeMonth() {
	global $datastore;
	$query = $datastore->query()
		->kind('Episode')
		->order('__key__', 'DESCENDING')
		->limit(1);
	$result = $datastore->runQuery($query);
	$result->rewind();
	if ($result->valid()) {
		$date = new DateTime($result->current()->get()["date"]);
		return getDateOfFirstOfMonth($date);
	} else {
		return null;
	}
}

function getEpisodeFromDb($bid) {
	global $datastore;
	$query = $datastore->query()
		->kind('Episode')
		->filter('__key__', '=', $datastore->key('Episode', $bid))
		->limit(1);
	$result = $datastore->runQuery($query);
	$result->rewind();
	if ($result->valid()) {
		$episode = (object)($result->current()->get());
		unset($episode->datetime);
		return $episode;
	} else {
		return null;
	}
}

function getEpisodeListFromDb($date, $howMany) {
	global $datastore;
	$query = $datastore->query()
		->kind('Episode')
		->order('datetime', 'DESCENDING')
		->filter('datetime', '>=', $date)
		->limit($howMany);
	$result = $datastore->runQuery($query);
	$episodeList = array();
	foreach ($result as $item) {
		$episode = (object)($item->get());
		unset($episode->datetime);
		array_push($episodeList, $episode);
	}
	return $episodeList;
}

function addEpisodeToDb($episode, $bid) {
	global $datastore;
	$episodeArray = (array)$episode;
	$transaction = $datastore->transaction();
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

	$transaction->upsert($entity);
	$transaction->commit();
}

function getGrid($date) {
	//http://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
	$url = 'http://www.davidjeremiah.org/site/radio_archives.aspx?bid=0&start=' . $date . '&sort_col=air_date&sort_dir=desc&view_more=true&grid_id=archives';
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

	//open connection
	$ch = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($ch, CURLOPT_URL, $srcurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

	//execute post
	$result = curl_exec($ch);

	// get lines
	$lines = explode("\n", $result);
	
	// get the 25th line
	$line = $lines[24];
	
	// get the URL
	$chopBeginning = substr($line, 49);
	$chopOnQuotes = explode('"', $chopBeginning);
	
	$url = $chopOnQuotes[0];
	//echo "getMediaURL($bid)";
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
	//echo "getMediaLength($url)";
	return $length;
}

function formatDateForPodcast($dateString) {
	$date = DateTime::createFromFormat("n/j/Y", $dateString);
	$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	$date->setTime(0, 0);
	return $date->format("D, d M Y H:i:s T");
}

function getNowDate() {
	$date = new DateTime();
	$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	return $date->format("D, d M Y H:i:s T");
}

function getEpisodeList($dateOfFirstOfMonth, $howMany = 100) {
	global $clearCache;
	$htmlSnippet = str_replace("&", "&amp;", getGrid($dateOfFirstOfMonth));
	$html = "<html><body>" . $htmlSnippet . "</body></html>";
	$dom = new DOMDocument;
	$dom->loadHTML($htmlSnippet);
	$xpath = new DOMXpath($dom);
	$rows = $xpath->query("/html/body/table/tr[@class='']");
	$length = $rows->length;
	$length = $length > $howMany ? $howMany : $length;
	$episodeList = array();
	for ($index = 0; $index < $length; $index++) {
		$row = $rows->item($index);
		$descMobileNode = $xpath->query("./td[@class='grid_cell title']/div", $row)->item(1);
		$bid = substr($descMobileNode->getAttribute("id"), 19);
		$episodeFromDb = null;
		if (! $clearCache) {
			$episodeFromDb = getEpisodeFromDb($bid);
		}
		if ($episodeFromDb) {
			array_push($episodeList, $episodeFromDb);
		} else {
			$episode = new stdClass();
			$episode->bid = $bid;
			$episode->date = formatDateForPodcast(trim($xpath->query("./td[@class='grid_cell air_date']", $row)->item(0)->nodeValue));
			$episode->title = utf8_decode(trim($xpath->query("./td[@class='grid_cell title']/div", $row)->item(0)->nodeValue));
			$episode->description = utf8_decode(trim($descMobileNode->nodeValue));
			$episode->mediaURL = getMediaURL($episode->bid);
			$episode->mediaLength = getMediaLength($episode->mediaURL);
			addEpisodeToDb($episode, $bid);
			array_push($episodeList, $episode);
		}
	}
	return $episodeList;
}

function getDateOfFirstOfMonth($date) {
	$date->setDate($date->format('Y'), $date->format('m'), 1);
	return $date;
}

function getPreviousMonth($date) {
	$date->setDate($date->format('Y'), $date->format('m') - 1, 1);
	return $date;
}

function getNLatestEpisodes($howMany) {
	global $clearCache, $cutOffDate;
	$date = new DateTime();
	$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	$episodeList = array();
	while (count($episodeList) < $howMany) {
		$date = getDateOfFirstOfMonth($date);
		if ($date < $cutOffDate) {
			break;
		}
		if ((! $clearCache) && $date < getLatestEpisodeMonth()) {
			$episodeListSegment = getEpisodeListFromDb($date, $howMany - count($episodeList));
		} else {
			$episodeListSegment = getEpisodeList($date->format("m/d/Y"), $howMany - count($episodeList));
		}
		$episodeList = array_merge($episodeList, $episodeListSegment);
		$date = getPreviousMonth($date);
	}
	return $episodeList;
}

$pubDate = getNowDate();
$episodeList = getNLatestEpisodes(30);

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
		<itunes:image href="https://storage.googleapis.com/turning-point-radio-podcast.appspot.com/Turning-Point-Cover-1400x1400.png"/>
		<itunes:category text="News &amp; Politics">
			<itunes:category text="Religion &amp; Spirituality"/>
				 <itunes:category text="Christianity">
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
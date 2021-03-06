<?php

require_once 'lib/t4php/twitter.class.php';
require_once 'conf/config.inc.php';
require_once 'lib/db/db.class.php';

$responses = array(
	'Have a {nice|great|excellent} {day|evening|night}!', // str_tpl example
	'I... have no response for you.' // static text
);

$db = Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
$db->connect();

$db->query("SET NAMES 'utf8'");

$twitter = new Twitter(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_SECRET);

function str_tpl($string, $regex = '/\{([^\{^\}]+)\}/'){
        if (preg_match($regex, $string, $matches)) {
                $match = explode('|', $matches[1]);
                $index = array_rand($match);
                $string = preg_replace($regex, $match[$index], $string, 1);
                return str_tpl($string);
        } else {
                return $string;
        }
}

function getUnrepliedTweets($interval=7200){
	$db = Database::obtain();
        $sql="SELECT `id`,`tweet_id`,`author`,`text` FROM `".TABLE_TWEETS."` WHERE `replied` = '0' AND `posted` > now() - interval " . $interval . " second ORDER BY `posted` DESC";
        $row = $db->query_first($sql);

        if(!empty($row['tweet_id'])) {
		$db->update(TABLE_TWEETS, array('replied' => '1'), "tweet_id='" . $row['tweet_id'] . "'");
                return array('tweet_id' => $row['tweet_id'], 'author' => $row['author'], 'text' => $row['text']);
        } else {
                return false;
        }

}

while (1) { // Main loop
	$tweet = getUnrepliedTweets(7200); // 3hs
	if ($tweet) {
		echo "Replying to " . $tweet["author"] . "\n";
		echo "Relevant text was: " . $tweet["text"] . "\n";
		try {
			$status = $twitter->send('@' . $tweet["author"] . ' ' . str_tpl($responses[array_rand($responses, 1)]), $tweet["tweet_id"]);
			if ($status) {
				echo "Tweet has been tweeted\n";
			} else {
				echo "Tweet failed...\n";
			}
		} catch (Exception $e) {
			echo "Twitter exception: $e\n";
		}
		echo "\n";

		$wait = rand(REPLY_MIN_INTERVAL, REPLY_MAX_INTERVAL);
		sleep($wait);
	} else {
		echo "Sleeping " . ceil(REPLY_SLEEP/60) . " minutes\n";
		echo "\n";
		sleep(REPLY_SLEEP);
	}
}

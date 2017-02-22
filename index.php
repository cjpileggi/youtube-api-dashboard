<?php
/**************************************************
*	Christopher Pileggi
*	YouTube API Dashboard (v 1.3)
*
* 	Creates a table containing information on
*	selected YouTube videos (i.e. likes and views)
*	using YouTube's API 3.0
*
**************************************************/
// Variables for MySQL
define(HOST, "");
define(USER, "");
define(PWORD, "");
define(DATABASE, "");
define(INSERT_VARS, "(''),('')"); //Add YouTube video ID's
?>
<html>
	<head>
		<style> 
			body {margin: 0;}
			div {width:100%;}
			td {font-size:23px; text-align:center;}
			th {background-color:#f99d33; color:white; font-size:20px; text-align:center;}
			table, th, td {border:2px solid black; border-collapse:collapse;}
			table tr:nth-child(odd) {background-color:#eee;}
		</style>
	</head>
	<body>
		<?php

		// YouTube video object
		class ytVid {

			public $name = '';
			public $vid = '';
			public $views = 0;
			public $likes = 0;
			public $comments = 0;
			public $publish = '';

			public function __construct($id){$this->vid = $id;}

			public function addInfo($v, $l, $c, $p, $n) {

				$this->name = $n;
				$this->views = $v;
				$this->likes = $l;
				$this->comments = $c;
				$this->publish = $p;
			}
		}

		//Calculate load time
		$cher = microtime(true);

		// Create temporary MySQL table based on constants
		$conn = mysqli_connect(HOST, USER, PWORD, DATABASE);

		if (!$conn) {die("Connection failed: " . mysqli_connect_error());}

		$sql = "DROP TABLE IF EXISTS youtube_dashboard";
		if (!mysqli_query($conn, $sql)){echo "Error dropping table: " . mysqli_error($conn);}

		$sql = "CREATE TABLE IF NOT EXISTS youtube_dashboard (id char(11) PRIMARY KEY) ENGINE=MyISAM DEFAULT CHARSET=latin1";
		if (!mysqli_query($conn, $sql)){echo "Error creating table: " . mysqli_error($conn);}

		$sql = "INSERT INTO youtube_dashboard (id) VALUES" . INSERT_VARS;
		if (!mysqli_query($conn, $sql)){echo "Error inserting into table: " . mysqli_error($conn);}

		$sql = "SELECT * from youtube_dashboard";
		//$sql = "SELECT group_id, group_name, youtube_video_id FROM smartpitch_metrics WHERE contest_year = '2015' AND active = 'Y' ORDER BY group_name";

		$groups = array();
		$jsons = array();
		$json_count = -1;
		$count = 0;

		// Create arrays of API URL strings and YouTube objects
		$result = mysqli_query($conn, $sql);
		if (mysqli_num_rows($result) > 0){

			while($row = mysqli_fetch_assoc($result)){

				if (!empty($row["id"])){

					// Limit 50 video ID's per string
					if ($count % 50 == 0){

						$json_count = $json_count + 1;
						$jsons[$json_count] = "https://www.googleapis.com/youtube/v3/videos?part=statistics,snippet&id=";
					}

					$jsons[$json_count] = $jsons[$json_count] . $row["id"] . ",";
					$groups[$count] = new ytVid((string)$row["id"]);

					//$groups[$count]->name = (string)$row["group_name"];

					$count++;

					//api 2.0 - $groups[$row["group_name"]] = (int)$JSON_Data->{'entry'}->{'yt$statistics'}->{'viewCount'};
				}
			}
		} 
		else {echo "0 results";}

		$count=0;
		$json_count = 0;

		
		while ($json_count < count($jsons)){

			//Get and decode JSON data using API key
			$JSON_Data = json_decode(file_get_contents(rtrim($jsons[$json_count], ",") . "&key=AIzaSyD64C6pAr5eXZWRZLsvjFFlg7KtC-b-04U"));

			// Assign YouTube objects based on JSON data
			foreach ($JSON_Data->items as $items){

				// Ensure that each video is assigned to the correct object
				// any videos without data present is skipped
				while($groups[$count]->vid != (string)$items->id){$count++;}

				$groups[$count]->addInfo( (int)$items->statistics->viewCount, (int)$items->statistics->likeCount, (int)$items->statistics->commentCount, (string)$items->snippet->publishedAt, (string)$items->snippet->title);

				$count++;
			}
			$json_count = $json_count + 1;
		}

		mysqli_close($conn);

		// Sort table by video views
		function cmp($a, $b){

			//echo $a->views . " " . $b->views . "<br />";

			if ($a->views == $b->views) {return 0;}

			return ($a->views > $b->views) ? -1 : 1;
		}

		usort($groups, "cmp"); 

		echo '<div><table><tr><th>Group Name</th><th>View Count</th><th>Like Count</th><th>Comment Count</th></tr>';

		foreach($groups as $x => $x_value){
				echo "<tr><td>" . $x_value->name . "</td><td>" . number_format($x_value->views) . "</td><td>" . number_format($x_value->likes) . "</td><td>" . number_format($x_value->comments) . "</td></tr>"; 
		}

		echo "</table></div>";

		//Calculate load time
		$ner = microtime(true);

		$time = number_format(($ner - $cher), 2);

		echo 'This page loaded in ', $time, ' seconds';

		?>
 	</body>
</html>
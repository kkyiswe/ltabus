<?php

if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
$id = $_GET["id"];
// $url = "http://datamall2.mytransport.sg/ltaodataservice/BusArrival?BusStopID=$id";
$url = "http://datamall2.mytransport.sg/ltaodataservice/BusArrivalv2/?BusStopCode=$id";

$creds = parse_ini_file(".creds.ini");

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL,$url);
$headers = array(
	'UniqueUserId:' . $creds["uniqueuserid"],
	'AccountKey:' . $creds["accountkey"],
);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result=curl_exec($ch);
$info = curl_getinfo($ch);
$errinfo = curl_error($ch);
curl_close($ch);

$j = json_decode($result, true);

function notimings($j) {
foreach ($j["Services"] as $service) {
	if (!empty($service["NextBus"]["EstimatedArrival"])) { return false; }
	}
	return true;
}

if (empty($j) || notimings($j)) {

	$dir = 'fail/' . date("Y-m-d");
	$fn = $dir . '/' . time() . ".txt";
	mkdir($dir, 0777, true);

	echo "<h1>No result from LTA API 😕</h1>";
	echo "<p><a href=$fn>Error log</a></p>";
	file_put_contents($fn, "[Curl info]\n" . print_r($info, true) . "\n[Curl Error]\n" . print_r($errinfo, true) . "\n[JSON]\n" . json_encode($j, JSON_PRETTY_PRINT));
	die();
}


if (isset($j["odata.error"])) { die ($j["odata.error"]["message"]["value"]); }

function my_sort($a, $b) {
    if ($a["NextBus"]["EstimatedArrival"] < $b["NextBus"]["EstimatedArrival"]) {
        return -1;
    } else if ($a["NextBus"]["EstimatedArrival"] > $b["NextBus"]["EstimatedArrival"]) {
        return 1;
    } else {
        return 0;
    }
}

usort($j["Services"], 'my_sort');
} else { $id = ""; }

?>
<!DOCTYPE html>
<html lang=en>
<head>
<title>Singapore bus arrival times</title>
<meta name=viewport content="width=device-width, initial-scale=1">
<meta name="apple-mobile-web-app-title" content="Stop <?=$id?>">
<link rel='icon' href='http://bus.dabase.com:7777/?stop=<?=$id?>'>
<link rel="apple-touch-icon" sizes="200x200" href="http://bus.dabase.com:7777/?stop=<?=$id?>">
<style>
body { padding: 5px; font-size: 120%; }
a { font-size: 110%; }
ul,ol { padding-left: 0; list-style: none; }
.buses li:before {
  content: "\1F68C";
  padding-right: 8px;
}

#stations li:before {
  content: "🚏";
  padding-right: 8px;
}

#stations li { white-space: nowrap; }

.busstopid { white-space: nowrap; display:inline-block; border-bottom: thin solid black; padding-bottom:2px; margin: 0 }

input[type=text] {
    font-size: 1em;
    width: 4em;
}

input[type=submit] {
    font-size: 1em;
}
</style>

<!-- <script>console.debug(<?php // echo $result;?>)</script> -->
</head>
<body>
<?php if($id) {?>
<h3 class=busstopid><?php echo "🚏" . $j["BusStopID"] . " " . $_GET["name"]; ?></h3>
<?php } ?>
<ul class=buses>
<?php

function tmark($s) {
	// var_dump(trim($s["Load"]));

	switch (trim($s["Load"])) {
	case "Seats Available":
		$color = "green";
		break;
	case "Standing Available":
		$color = "orange";
		break;
	case "Limited Standing":
		$color = "red";
		break;
	default:
		$color = "black";
		break;
	}

	// Monitored is true is it is tracked in real time. Otherwise it's just a schedule based prediction
	$mcss = "";
	if ((isset($s["Monitored"])) && ($s["Monitored"] == "False")) {
		$mcss = "opacity: 0.5;";
	}

	return '<time style="' . $mcss . 'color: ' . $color . '" dateTime="' . $s["EstimatedArrival"] . '">' . $s["EstimatedArrival"] . '</time>';
}

if (isset($j)) {
foreach ($j["Services"] as $service) {
	if (empty($service["NextBus"]["EstimatedArrival"])) { continue; }
	echo "<li>";
	echo "<strong><a href='https://busrouter.sg/#/services/" . $service["ServiceNo"] . "'>" . $service["ServiceNo"] . "</a></strong> ";
	echo tmark($service["NextBus"]) . ", ";
	if (! empty($service["NextBus2"]["EstimatedArrival"])) {
		echo tmark($service["NextBus2"]);
	}
	if (! empty($service["NextBus3"]["EstimatedArrival"])) {
		echo ", " . tmark($service["NextBus3"]);
	}
	echo "</li>\n";
}
}
?>
</ul>
<?php if(! empty($id)) {?>
<h4>Last updated: <span id=lastupdated></span></h4>
<?php } ?>
<form>
<label for=id>Bus stop #</label>
<input id=id required type=text pattern="[0-9]{5}" value="<?php if(isset($id)) { echo $id; }?>" name=id>
<input type=submit>
</form>

<a href=/map.html>Map of Singapore bus stops</a></p>

<ol id=stations></ol>


<script>
function countdown(id, time) {
	if (! id) { return; }
	// console.log(id,time);
	var seconds =  time / 1000;
	if (Math.abs(seconds) > 60) {
		id.innerHTML = parseInt(seconds / 60) + "m";
	} else {
		id.innerHTML = parseInt(seconds) + "s";
	}
	setTimeout(countdown,1000, id, time - 1000);
}

window.addEventListener('load', function() {
	var timings = document.getElementsByTagName("time");
	var now = new Date();
	for (var i = 0; i < timings.length; i++) {
		var arr = new Date(timings[i].getAttribute("datetime"));
		var elapsed = arr.getTime() - now.getTime();
		countdown(timings[i], elapsed);
	}
	var lastupdated = document.getElementById("lastupdated");
	countdown(lastupdated, Date.now() - now);


	slog = (JSON.parse(localStorage.getItem("history")) || {});

<?php if (! empty($id)) { ?>
	if (typeof slog['<?php echo $id;?>'] === "undefined") {
		slog['<?php echo $id;?>'] = {};
		slog['<?php echo $id;?>'].count = 0;
	}
	try {
		slog['<?php echo $id;?>'].count++;
	} catch(e) { console.log(e); }

	<?php if ($_GET["name"]) { ?>
	slog['<?php echo $id;?>'].name = "<?php echo $_GET["name"]; ?>";
	<?php } ?>

	<?php if (isset($_GET["lat"]) && is_numeric($_GET["lat"])) { ?>
	slog['<?php echo $id;?>'].x = "<?php echo $_GET["lat"]; ?>";
	<?php } ?>

	<?php if (isset($_GET["lon"]) && is_numeric($_GET["lon"])) { ?>
	slog['<?php echo $id;?>'].y = "<?php echo $_GET["lon"]; ?>";
	<?php } ?>

	console.debug(slog);
	localStorage.setItem('history', JSON.stringify(slog));
	console.debug(localStorage['history']);
<?php } ?>

	var sortable = [];
	for (var station in slog) {
		sortable.push([station, slog[station]])
	}
	sortable.sort(function(a, b) { return a[1].count - b[1].count })
	// console.debug(sortable);
	var ul = document.getElementById("stations");
	for (var i = sortable.length - 1; i >= 0; i--) {
		var key = sortable[i][0];
		var value = sortable[i][1];
		// console.log(key, value);

		var li = document.createElement("li");
		var link = document.createElement('a');
		if (value.name) {
		link.setAttribute('href', '/?id=' + key + '&name=' + encodeURI(value.name));
		link.appendChild(document.createTextNode(key + " " + value.name + ' (' + value.count + ')'));
		} else {
		link.setAttribute('href', '/?id=' + key);
		link.appendChild(document.createTextNode(key));
		}
		li.appendChild(link);
		ul.appendChild(li);
	}

}, false);
</script>
<footer><a href=https://github.com/kaihendry/ltabus>Source code</a>&diam;<a href="mailto:hendry+bus@iki.fi">Please email feedback</a>&diam;<a href=http://mrt.dabase.com/>Train map</a></footer>
</body>
</html>

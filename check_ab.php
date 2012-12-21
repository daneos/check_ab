#!/usr/bin/php
<?php
	
	$STATE_OK=0;
	$STATE_WARNING=1;
	$STATE_CRITICAL=2;
	$STATE_UNKNOWN=3;

	$url = FALSE;
	$file = FALSE;
	$reqcnt = explode(':', "1000:100");
	$percent = explode(':', "5:10");
	$db = explode(':', "86400:MAX");
	$num = 7;

	function usage()
	{
		echo "Usage: ".$argv[0]." <options>\n";
		echo "Compare Apache benchmark req/sec with RRD file\n";
		echo "Options:\n";
		echo "-u URL (required)\n";
		echo "-f RRD file (required)\n";
		echo "-n request count (defaults to 1000)\n"; 
		echo "-c concurrent request count (defaults to 100)\n";
		echo "-C percent for CRIT (defaults to 5)\n";
		echo "-W percent for WARN 10)\n";
		echo "-d RRD database to check (defaults to MAX)\n";
		echo "-D database by time (defaults to 86400)\n";
		echo "-a number of last entries to check (defaults to 7)\n";
		echo "-h shows this help message\n";
		echo "Example: ".$argv[0]." -u http://example.com/ -f test.rrd -n 10000 -c 100 -C 10 -W 20 -d AVERAGE -D 300 -a 10\n";
	}

	function get_max($rrd, $num, $db)
	{
		$max = NULL;
		foreach($rrd->rra as $rra)
		{
			if($rra->pdp_per_row != $db[0]/$rrd->step) continue;
			if($rra->cf != $db[1]) continue;
			$rows = $rra->database->row;
			break;
		}
		$rowcount = count($rows);
		for($i=0; $i < $num; $i++)
		{
			$cval = sscanf($rows[$rowcount - $i - 1]->v, "%E");
			if($cval[0] > $max) $max = $cval[0];
		}
		return $max;
	}

	$options = getopt("hd:u:a:f:n:c:C:W:D:");
	foreach($options as $option=>$val)
	{
		switch($option)
		{
			case 'u':
				$url = $val;
				break;
			case 'f':
				$file = $val;
				break;
			case 'n':
				$reqcnt[0] = $val;
				break;
			case 'c':
				$reqcnt[1] = $val;
			case 'C':
				$percent[0] = $val;
				break;
			case 'W':
				$percent[1] = $val;
				break;
			case 'd':
				$db[0] = $val;
				break;
			case 'D':
				$db[1] = $val;
				break;
			case 'a':
				$num = $val;
				break;
			case 'h':
				usage();
				return 0;
				break;
			default:
				echo "Unknown option: -$option\n";
				usage();
				return 1;
				break;
		}
	}

	if(!$url)
	{
		echo "No URL specified.\n";
		return 1;
	}
	if(!$file)
	{
		echo "No filename specified.\n";
		return 1;
	}

	exec("ab -q -n ".escapeshellarg($reqcnt[0])." -c ".escapeshellarg($reqcnt[1])." ".escapeshellarg($url), $ab);
	$ab_out = implode('', $ab);
	exec("rrdtool dump ".escapeshellarg($file), $rrd);
	$rrdxml = simplexml_load_string(implode('', $rrd));

	preg_match("/Requests per second\:.*?([0-9\.]+) \[#\/sec\] \(mean\)/", $ab_out, $match);
	$rps = $match[1];

	if(!isset($rps))
	{
		echo "UNKNOWN: ab failed\n";
		return $STATE_UNKNOWN;
	}

	$max = get_max($rrdxml, $num, $db);
	if($max == NULL)
	{
		echo "UNKNOWN: Failed to parse RRD\n";
		return $STATE_UNKNOWN;
	}
	$reqperc = 100 - (($max / $rps)*100);
	if($reqperc < (float)$percent[0])
	{
		echo "CRITICAL: Server can handle ".sprintf("%.3f", $reqperc)."% more requests\n";
		echo "Tested: ".$rps."; In RRD: ".$max.";\n";
		return $STATE_CRITICAL;
	}
	if($reqperc < (float)$percent[1])
	{
		echo "WARNING: Server can handle ".sprintf("%.3f", $reqperc)."% more requests\n";
		echo "Tested: ".$rps."; In RRD: ".$max.";\n";
		return $STATE_WARNING;
	}
	echo "OK: Requests per second: ".$rps."\n";
	echo "Tested: ".$rps."; In RRD: ".$max."; Percent: ".sprintf("%.3f", $reqperc).";\n";
	return $STATE_OK;
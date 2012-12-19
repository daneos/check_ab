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
	$db = explode(':', "86400:AVERAGE");

	function usage()
	{
		echo "Usage: ".$argv[0]." <options>\n";
		echo "Compare Apache benchmark req/sec with RRD file\n";
		echo "Options:\n";
		echo "-u URL (required)\n";
		echo "-f RRD file (required)\n";
		echo "-n (x:y) x-request count y-one time request count (defaults to 1000:100)";
		echo "-p (x:y) x-percent for CRIT y-percent for WARN (defaults to 5:10)";
		echo "-d (x:y) RRD database to check x-seconds y-name (defaults to (86400:AVERAGE)"
		echo "-h shows this help message\n";
		echo "Example: ".$argv[0]." -u http://example.com/ -f test.rrd -n 10000:100 -p 10:20 -d 300:MAX\n";
	}

	$options = getopt("hu:f:n:p:d:");
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
				$reqcnt = explode(':', $val);
				break;
			case 'p':
				$percent = explode(':', $val);
				break;
			case 'd':
				$db = explode(':', $val);
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

	$regexp="~Requests per second\:.*[0-9\.]+~";
	for($i=0; $i < count($ab); $i++)
	{
		if(preg_match($regexp, $ab[$i], $match))
		{
			$exploded = explode(':', $match[0]);
			$rps = trim($exploded[1]);
			break;
		}
	}
	echo "$rps\n";

	for($i=0; $i < count($rrdxml->rra); $i++)
	{
		if($rrdxml->rra[$i]->pdp_per_row == $db[0]/$rrdxml->step)
		{
			if($rrdxml->rra[$i]->cf == $db[1])
			{
				for($j=0; $j < count($rrdxml->rra[$i]->database->row); $j++)
				{
					$reqperc = 100 - (($rrdxml->rra[$i]->database->row[$j]->v / $rps)*100);
					if($reqperc < $percent[0])
					{
						echo "CRITICAL: Server can handle only ".$reqperc." more requests\n";
						return STATE_CRITICAL;
					}
					if($reqperc < $percent[1])
					{
						echo "WARNING: Server can handle only ".$reqperc." more requests\n";
						return STATE_WARNING;
					}

				}
			}
		}
	}
	echo "OK: Requests per second: ".$rps."\n";
	return STATE_OK;
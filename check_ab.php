#!/usr/bin/php
<?php
	
	$STATE_OK=0;
	$STATE_WARNING=1;
	$STATE_CRITICAL=2;
	$STATE_UNKNOWN=3;

	function usage()
	{
		echo "Usage: ".$argv[0]." <options>\n";
		echo "Compare Apache benchmark req/sec with RRD file\n";
		echo "Options:\n";
		echo "-u URL (required)\n";
		echo "-f RRD file (required)\n";
		echo "-n (x:y) x-request count y-one time request count (defaults to 1:1)"
		echo "-h shows this help message\n";
		echo "Example: ".$argv[0]." -u http://example.com/ -f test.rrd -n 10000:100\n";
	}

	$options = getopt("hf:t:n:");
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
	if(!$reqcnt)
	{
		$reqcnt = explode(':', "1:1")
	}

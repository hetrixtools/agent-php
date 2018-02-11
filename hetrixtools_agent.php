<?php
#
#	HetrixTools Server Monitoring Agent (PHP)
#	version 1.05-php
#	Copyright 2018 @  HetrixTools
#	For support, please open a ticket on our website https://hetrixtools.com
#
#
#		DISCLAIMER OF WARRANTY
#
#	The Software is provided "AS IS" and "WITH ALL FAULTS," without warranty of any kind, 
#	including without limitation the warranties of merchantability, fitness for a particular purpose and non-infringement. 
#	HetrixTools makes no warranty that the Software is free of defects or is suitable for any particular purpose. 
#	In no event shall HetrixTools be responsible for loss or damages arising from the installation or use of the Software, 
#	including but not limited to any indirect, punitive, special, incidental or consequential damages of any character including, 
#	without limitation, damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses. 
#	The entire risk as to the quality and performance of the Software is borne by you, the user.
#
///////////////////////
// Settings
///////////////////////

// Agent Version (do not change)
$version = '1.05';

// SID (Server ID)
// You need to manually edit this value, and replace it with the SID given to you by the HetrixTools platform
$SID = 'SIDPLACEHOLDER';

// Network Interface
// If eth0 is not the correct interface name, you will need to modify the value below
$inet = 'eth0';

///////////////////////
// Settings
///////////////////////
################################################
## CAUTION: Do not edit any of the code below ##
################################################
///////////////////////
// Functions
///////////////////////
function get_network_stats($interface) {
    $network = file('/proc/net/dev');
    $net = find_in_array($interface,$network);
    $net = preg_split('/\s+/',$net);
    return array($net[2],$net[10]);
}
function get_network_usage($net1,$net2) {
    $rx = $net2[0] - $net1[0];
    $tx = $net2[1] - $net1[1];
    return array($rx,$tx);
}
function find_in_array($needle,$subject) {
    foreach($subject as $key => $value){
        if(stristr($value,$needle)){
            return $value;
        }
    }
}
function intval_from_ram($needle,$subject) {
    $val = explode(":",find_in_array($needle,$subject));
    $val = intval($val[1]);
    return $val;
}
function get_cpu_stats() {
	$data = file('/proc/stat');
	$cores = array();
	foreach($data as $line) {
		if(preg_match('/^cpu[0-9]/',$line)) {
			$info = explode(' ',$line);
			$cores[] = array('user' => $info[1],'nice' => $info[2],'sys' => $info[3],'idle' => $info[4],'iowait' => $info[5]);
		}
	}
	return $cores;
}
function get_cpu_usage($stat1,$stat2) {
	if(count($stat1) !== count($stat2)) {return false;}
	$total_idle = 0; $total_iowait = 0;
	for($i=0,$l=count($stat1);$i<$l;$i++) {
		$dif = array();
		$dif['user'] = $stat2[$i]['user'] - $stat1[$i]['user'];
		$dif['nice'] = $stat2[$i]['nice'] - $stat1[$i]['nice'];
		$dif['sys'] = $stat2[$i]['sys'] - $stat1[$i]['sys'];
		$dif['idle'] = $stat2[$i]['idle'] - $stat1[$i]['idle'];
		$dif['iowait'] = $stat2[$i]['iowait'] - $stat1[$i]['iowait'];
		$total = array_sum($dif);
		$total_idle += (1000*$dif['idle']/$total+5)/10;
		$total_iowait += (1000*$dif['iowait']/$total+5)/10;
	}
	$cpu_usage = round(100 - $total_idle / count($stat1),2);
	$cpu_iowait = round($total_iowait / count($stat1),2);
	return array($cpu_usage,$cpu_iowait,count($stat1));
}
function microtime_float() {
	list($usec,$sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}
///////////////////////
// Functions
///////////////////////

// Start time
$start = microtime_float();

// Get initial stats
$cpu1 = get_cpu_stats();
$net1 = get_network_stats($inet);

// Sleep
$sleep = 60 - intval(date('s'));
sleep($sleep);

// Get secondary stats
$cpu2 = get_cpu_stats();
$net2 = get_network_stats($inet);

// End time
$end = microtime_float();

// Calculate running time
$seconds = round($end - $start);

// Calculate Usage
$cpu = get_cpu_usage($cpu1,$cpu2);
$net = get_network_usage($net1,$net2);

// Operating System
if(is_readable('/etc/lsb-release')) {
    $os = file('/etc/lsb-release');
    $os = explode('"',$os[3]);
    $os = $os[1];
}
elseif(is_readable('/etc/debian_version')) {$os = "Debian ".file_get_contents('/etc/debian_version');}
elseif(is_readable('/etc/redhat-release')) {$os = file_get_contents('/etc/redhat-release');}
elseif(is_readable('/proc/sys/kernel/osrelease')) {$os = "Linux ".file_get_contents('/proc/sys/kernel/osrelease');}
else {$os = "Linux";}
$os = base64_encode($os);

// Uptime
$uptime = intval(file_get_contents('/proc/uptime'));

// CPU Info
$cpu_info = file('/proc/cpuinfo');

// CPU Model
$cpu_model = explode(": ",$cpu_info[4]);
$cpu_model = base64_encode($cpu_model[1]);

// CPU Speed
$cpu_speed = explode(": ",$cpu_info[7]);
$cpu_speed = intval($cpu_speed[1]);

// CPU Cores
$cpu_cores = $cpu[2];

// CPU Usage
$cpu_usage = $cpu[0];

// CPU IOWAIT
$cpu_iowait = $cpu[1];

// RAM Info
$ram_info = file('/proc/meminfo');

// RAM Size
$ram_size = intval_from_ram('MemTotal',$ram_info);

// RAM Usage
$ram_free = intval_from_ram('MemFree',$ram_info) + intval_from_ram('Buffers',$ram_info) + intval_from_ram('Cached',$ram_info);
$ram_usage = round(100 - (($ram_free*100)/$ram_size),2);

// Swap Size
$swap_size = intval_from_ram('SwapTotal',$ram_info);

if($swap_size > 0) {
// Swap Usage
	$swap_free = intval_from_ram('SwapFree',$ram_info);
	$swap_usage = round(100 - (($swap_free*100)/$swap_size),2);
} else {
	$swap_usage = 0;
}

// Disk Usage
$disk_total = disk_total_space(dirname(__FILE__));
$disk_used = $disk_total-disk_free_space(dirname(__FILE__));
$disk_path = explode("/",dirname(__FILE__));
$disk_path = "/".$disk_path[1];
$disk = base64_encode($disk_path.",".$disk_total.",".$disk_used.";");

// Network Usage
$rx = round($net[0]/$seconds); // Incoming
$tx = round($net[1]/$seconds); // Outgoing

// Arrange the post data
$post_data = "$os|$uptime|$cpu_model|$cpu_speed|$cpu_cores|$cpu_usage|$cpu_iowait|$ram_size|$ram_usage|$swap_size|$swap_usage|$disk|$rx|$tx|";
$post = "v=$version&a=1&s=$SID&d=$post_data";

// Log the current post string (for debugging)
file_put_contents('hetrixtools_agent.log',$post);

// Post the data to HetrixTools
$ch = curl_init('https://sm.hetrixtools.com');
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,0);
curl_exec($ch);

?>

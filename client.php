#!/Applications/XAMPP/xamppfiles/bin/php
<?php
$bind_addr = '10.10.17.17';
$bind_port = '9090';

$fp = stream_socket_client("tcp://$bind_addr:$bind_port", $errno, $errstr, 30);

//stream_filter_append($fp, "bzip2.decompress", STREAM_FILTER_READ);
//stream_filter_append($fp, "str.decode", STREAM_FILTER_READ);

//stream_filter_append($fp, "str.encode", STREAM_FILTER_WRITE);
//stream_filter_append($fp, "bzip2.compress", STREAM_FILTER_WRITE);

$out .= "agi_network: yes\r\n";
$out .= "agi_request: agi://10.10.17.17:9090\r\n";
$out .= "agi_channel: Console/dsp\r\n";
$out .= "agi_language: en\r\n";
$out .= "agi_type: Console\r\n";
$out .= "agi_uniqueid: 1280437933.4\r\n";
$out .= "agi_version: 1.6.0.17\r\n";
$out .= "agi_callerid: unknown\r\n";
$out .= "agi_calleridname: unknown\r\n";
$out .= "agi_callingpres: 0\r\n";
$out .= "agi_callingani2: 0\r\n";
$out .= "agi_callington: 0\r\n";
$out .= "agi_callingtns: 0\r\n";
$out .= "agi_dnid: 3089\r\n";
$out .= "agi_rdnis: unknown\r\n";
$out .= "agi_context: default\r\n";
$out .= "agi_extension: 3089\r\n";
$out .= "agi_accountcode: stl\r\n";
$out .= "agi_priority: 2\r\n";
$out .= "agi_enhanced: 0.0\r\n";
$out .= "agi_threadid: 140422789798160\r\n";
$out .= "\r\n";

if (!$fp) {
   echo "$errstr ($errno)<br />\n";
} else {
	for ($x=0;$x<=999;$x++)
	{
		fwrite($fp, $out);
		//sleep(1);
//   while (!feof($fp)) {
//       var_dump(fgets($fp, 1024));
//   }
	}
   fclose($fp);
}

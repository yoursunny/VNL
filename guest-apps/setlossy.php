<?php
# setlossy.php command_file ifid lossy

$command_file = @$argv[1];
$ifid = @intval($argv[2]);
$lossy = @intval($argv[3]);

if (!file_exists($command_file) || !($lossy>=0 && $lossy<=100)) {
	die("setlossy.php command_file ifid lossy\n");
}

$cmd = pack("CC",$ifid,$lossy);
file_put_contents($command_file,$cmd);

?>

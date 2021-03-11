<?php 

$command = escapeshellcmd('/var/www/html/wind/mypythonscript.py');
$output = shell_exec($command);
echo $output;

?>

<?php
$manifest = ['forms' => []];
foreach (glob("*.php") as $filename) {
	if ($filename == basename(__FILE__)) {
		continue;
	}
    $lines = file($filename);
    foreach ($lines as $line) {
    	if (substr_count($line, ' * @') != 1) {
    		continue;
    	}
    	if (substr_count($line, '* @version') == 1) {
    		$manifest['forms'][basename($filename, '.php')] = trim(str_replace('* @version', '', $line));
    	}
    }
}
file_put_contents('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
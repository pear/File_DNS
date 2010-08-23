--TEST--
File_DNS::isIP() tests
--FILE--
<?php
set_include_path(
    realpath(dirname(dirname(__FILE__))) . PATH_SEPARATOR . get_include_path()
);
require_once 'DNS.php';

$dns = new File_DNS;

if ($dns->isIP('1.1.1.1')) {
	echo ".";
} else {
	echo "FAIL: with shortest possible IP\n";
}

if ($dns->isIP('2.4.4.41')) {
	echo ".";
} else {
	echo "FAIL: with longer address\n";
}

if ($dns->isIP('244.244.244.244')) {
	echo ".";
} else {
	echo "FAIL: an address of max allowed length\n";
}

if ($dns->isIP('0.0.0.0')) {
	echo ".";
} else {
	echo "FAIL: all zeroes\n";
}

if ( ! $dns->isIP('400.0.0.0')) {
	echo ".";
} else {
	echo "FAIL: one group larger than allowed\n";
}


?>
--EXPECT--
.....

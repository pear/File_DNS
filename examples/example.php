<?php
include("File/DNS.php");

$zone = new File_DNS;
$load = $zone->load('example.net', 'example.net');
$zone->setName('www2', 'www', 'a');
$zone->setDomainName('example.org');
$zone->setValue('127.0.0.1', '');

echo $zone->toString();
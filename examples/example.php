<?php
/**
 * The File_DNS example code.
 *
 * PHP versions 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  File
 * @package   File_DNS
 * @author    Cipriano Groenendal <cipri@php.net>
 * @copyright 2004-2005 Cipriano Groenendal <cipri@php.net>
 * @license   http://www.php.net/license/3_0.txt PHP License 3.0
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/File_DNS
 */

require_once 'File/DNS.php';

$zone = new File_DNS;
$load = $zone->load('example.net', 'example.net');
$zone->setName('www2', 'www', 'a');
$zone->setDomainName('example.org');
$zone->setValue('127.0.0.1', '');

echo $zone->toString();
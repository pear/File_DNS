<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * The File_DNS_Exception class is the Error class for File_DNS
 *
 * PHP versions 5.3.0
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  File
 * @package   File_DNS
 * @author    Jonathan Creasy <jonathan.creasy@gmail.com>
 * @copyright 2011-2012 Jonathan Creasy <jonathan.creasy@gmail.com>
 * @license   http://www.php.net/license/3_0.txt PHP License 3.0
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/File_DNS
 */

// {{{ requires

/**
 * require PEAR_Exception
 *
 * This package depends on PEAR to raise errors.
 */
require_once 'PEAR/Exception.php';

// {{{ constants

/**
 * Cannot open file.
 */
define('FILE_DNS_FILE_READALL_FAILED',  -1);

/**
 * Cannot save to file.
 */
define('FILE_DNS_FILE_WRITE_FAILED',    -2);

/**
 * SOA Parse Failed.
 */
define('FILE_DNS_PARSE_SOA_FAILED',     -3);

/**
 * RR Parse failed.
 */
define('FILE_DNS_PARSE_RR_FAILED',      -4);

/**
 * Parsing 1X to seconds failed.
 */
define('FILE_DNS_PARSE_TIME_FAILED',    -5);

/**
 * Parsing seconds to 1X failed.
 */
define('FILE_DNS_PARSEBACK_TIME_FAILED', -6);

/**
 * Can't render, zone not loaded yet.
 */
define('FILE_DNS_RENDER_NOT_LOADED',    -7);

/**
 * Can't set domain. Invalid Domain name.
 */
define('FILE_DNS_INVALID_DOMAIN',       -8);

/**
 * Can't update/set SOA
 */
define('FILE_DNS_UPDATE_SOA_FAILED',    -9);

// }}}
/**
* an RFC1033 style zonefile editor
*
* The File::DNS class provides an Object Oriented
* interface to read, edit and create DNS Zonefiles.
*
* @category  File
* @package   File_DNS
* @author    Jonathan Creasy <jonathan.creasy@gmail.com>
* @copyright 2011-2012 Jonathan Creasy <jonathan.creasy@gmail.com>
* @license   http://www.php.net/license/3_0.txt PHP License 3.0
* @version   Release: @version
* @link      http://pear.php.net/package/File_DNS
*/
class File_DNS_Exception extends PEAR_Exception
{
}
?>
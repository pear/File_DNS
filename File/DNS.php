<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * The File_DNS class is editor for RFC1033 style zonefiles.
 *
 * The File::DNS class provides an OO interface
 * to read, write, edit and create DNS Zones.
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
 * @author    Cipriano Groenendal <cipri@php.net>
 * @copyright 2004-2005 Cipriano Groenendal <cipri@php.net>
 * @license   http://www.php.net/license/3_0.txt PHP License 3.0
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/File_DNS
 */

// {{{ requires

/**
 * require PEAR
 *
 * This package depends on PEAR to raise errors.
 */
require_once 'PEAR.php';

/**
 * require File
 *
 * File allows us to easily read
 * multiple different sort of sources.
 **/
require_once 'File.php';

// }}}
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
// {{{ File_DNS

/**
 * an RFC1033 style zonefile editor
 *
 * The File::DNS class provides an Object Oriented
 * interface to read, edit and create DNS Zonefiles.
 *
 * @category  File
 * @package   File_DNS
 * @author    Cipriano Groenendal <cipri@php.net>
 * @copyright 2004-2005 Cipriano Groenendal <cipri@php.net>
 * @license   http://www.php.net/license/3_0.txt PHP License 3.0
 * @version   Release: @version@
 * @link      http://pear.php.net/package/File_DNS
 * @link      http://www.rfc-editor.org/rfc/rfc1033.txt
 * @link      http://www.rfc-editor.org/rfc/rfc1537.txt
 * @link      http://www.rfc-editor.org/rfc/rfc2308.txt
 * @todo      Add delRecord functions for basic editing.
 * @todo      Add create() function to start from scratch.
 * @todo      Fix examples
 */
class File_DNS
{

    // {{{ properties

    /**
     * contains the domainname of the loaded zone
     *
     * The domainname will automaticly be appended
     * to any and all records. Unused if set to null.
     *
     * @var string
     * @see load, _parseZone
     * @access private
     */
    private $_domain;

    /**
     * contains the filename of the loaded zone
     *
     * This is the currently loaded filename, and is
     * also used during save to write to again.
     *
     * @var string
     * @see load, save
     */
    private $_filename;

    /**
     * SOA Record of the loaded zone.
     *
     * This contains all the relevant data stored
     * in the SOA (Start of Authority) record.
     * It's stored in an associative array, that
     * should be pretty self-explaining.
     * <pre>
     * Array
     *   (
     *       [name] => example.com.
     *       [ttl] => 345600
     *       [class] => IN
     *       [origin] => ns1.example.com.
     *       [person] => hostmaster.example.com.
     *       [serial] => 204041514
     *       [refresh] => 14400
     *       [retry] => 1800
     *       [expire] => 86400
     *       [minimum] => 10800
     *   )
     * </pre>
     * @var array
     * @see _parseZone, _parseSOA, setSOAValue.
     */
    private $_SOA;

    /**
     * contains all the records in this zone.
     *
     * An unindexed array of Resource Records (RR's)
     * for this zone. Each item is a separate RR.
     * It's format should be pretty self explaining.
     * See manual for exact definition.
     *
     * @var array
     * @see _parseZone
     */
    private $_records;

    /**
     * contains all the GENERATE directives in this zone.
     *
     * @var array
     * @see _parseZone
     */
    private $_generate;


    /**
     * contains all supported Resource Records.
     *
     * This list contains all supported resource records.
     * This currently is:
     *
     * SOA
     * A
     * AAAA
     * NS
     * MX
     * CNAME
     * PTR
     * TXT
     * SRV
     *
     * @var array
     * @see _parseRR
     */
    private $_types;

    /**
     * zonefile modification check
     *
     * This checks whether the loaded zonefile has been modified.
     * If so, we need to generate a new serial when we render it.
     *
     * @var bool
     * @see generateZone, setDomainName, setTTL,
     * @see addRecord, replaceRecord, setRecord, delRecord
     */
    private $_isModified;


    /**
     * package Version
     *
     * @var string
     */
    public $version;


    // }}}
    // {{{ Constructor
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_domain = null;
        $this->_filename = null;
        $this->_SOA = Array();
        $this->_records = Array();
        $this->_generate = Array();
        $this->_types = array(
        'SOA', 'A', 'AAAA', 'NS', 'MX', 'CNAME', 'PTR', 'TXT', 'SRV'
        );
        $this->_isModified = false;
        $this->version = '@version@';  
    }
    // }}}
    // {{{ load()

    /**
     * cleans the object, then loads the specified zonefile.
     *
     * @param string $domain   domainname of this zone
     * @param string $zonefile filename of zonefile to load.
     *                          Can be anything that PEAR::File can read.
     * @param int    $lock     type of lock to establish on the zonefile.
     *                          Set to LOCK_SH for a shared lock (reader)
     *                          Set to LOCK_EX for an exclusive lock (writer)
     *                          Add LOCK_NB if you don't want locking to block
     *
     * @return bool  true on success, PEAR Error on failure.
     * @access public
     */
    public function load($domain, $zonefile, $lock = false)
    {
        //First, clean off the object.
        $this->free();
        $zone = File::readAll($zonefile, $lock);
        if (PEAR::isError($zone)) {
            //File package doesn't have codes associated with errors,
            //so raise our own.
            throw new Pear_Exception(
                'Unable to read file ' . $zonefile, FILE_DNS_FILE_READALL_FAILED
            );
        }

        try
        {
            $ret = $this->setDomainName($domain);
        } catch (Pear_Exception $e)
        {
            throw $e;
        }

        $this->_filename = $zonefile;

        try
        {
            $parse = $this->_parseZone($zone);
        } catch (Pear_Exception $e)
        {
            throw $e;
        }

        $this->_isModified = false;
        return $parse;
    }

    // }}}
    // {{{ Parsing
    // {{{ _parseZone()


    /**
     * parses a zonefile to object
     *
     * This function parses the zonefile and saves the data
     * collected from it to the _domain, _SOA and _records variables.
     *
     * @param string $zone The zonefile to parse.
     *
     * @return bool  true on success, PEAR Error on failure.
     */
    private function _parseZone($zone)
    {
        //RFC1033: A semicolon (';') starts a comment; the
        //remainder of the line is ignored.
        $zone = preg_replace('/(;.*)$/m', '', $zone);

        //FIXME
        //There has to be an easier way to do that, but for now it'll do.

        //RFC1033: Parenthesis ('(',')') are used to group
        //data that crosses a line boundary.
        $zone = preg_replace_callback(
            '/(\([^()]*\))/', function ($matches) {
            return str_replace("\\n", "", $matches[0]);
            }, $zone
        );
        $zone = str_replace('(', '', $zone);
        $zone = str_replace(')', '', $zone);


        /*
         * Origin is the current origin(@) that we're at now.
        * OriginFQDN is the FQDN origin, that gets appended to
        * non FQDN origins.
        *
        * FQDN == Fully Qualified Domain Name.
        *
        * Example:
        *
        *  $ORIGIN example.com.
        *  $ORIGIN sub1
        *  @ is sub1.example.com.
        *  $ORIGIN sub2
        *  @ is sub2.example.com.
        *  $ORIGIN new.sub3.example.com.
        *  @ is new.sub3.example.com.
        */

        $originFQDN = $origin = $current = $this->_domain . '.';
        $ttl = 86400; //RFC1537 advices this value as a default TTL.

        $zone = explode("\n", $zone);
        foreach ($zone as $line) {
            $line = rtrim($line);
            $line = preg_replace('/\s+/', ' ', $line);

            $record = array();
            if (!$line) {
                //Empty lines are stripped.
            } elseif (preg_match('/^\$TTL([^0-9]*)([0-9]+)/i', $line, $matches)) {
                //RFC 2308 defins the $TTL keyword as default TTL from here.
                $ttl = intval($matches[2]);
            } elseif (preg_match('/^\$ORIGIN (.*\.)/', $line, $matches)) {
                //FQDN origin. Note the trailing dot(.)
                $origin = $originFQDN = trim($matches[1]);
            } elseif (preg_match('/^\$ORIGIN (.*)/', $line, $matches)) {
                //New origin. Append to current origin.
                $origin = trim($matches[1]) . '.' . $origin;
            } elseif (preg_match('/^\$GENERATE (.*)/', $line, $matches)) {
                // GENERATE STATEMENT
                // The $GENERATE directive is a BIND extension and not part 
                // of the standard zone file format.
                // http://www.bind9.net/manual/bind/9.3.2/Bv9ARM.ch06.html#id2566761
                $this->_generate[] = $matches[1];
            } elseif (stristr($line, ' SOA ')) {
                if ($this->_SOA) {
                    //SOA already set. Only one per zone is possible.
                    //Done parsing.
                    //A second SOA is added by programs such as dig,
                    //to indicate the end of a zone.
                    break;
                }

                try
                {
                    $soa = $this->_parseSOA($line, $origin, $ttl);
                    $soa = $this->setSOAValue($soa);
                } catch (Pear_Exception $e)
                {
                    throw $e;
                }
            } else {
                try
                {
                    $rr = $this->_parseRR($line, $origin, $ttl, $current);
                } catch (Pear_Exception $e)
                {
                    throw $e;
                }
                $current = $rr['name'];
                $this->_records[] = $rr;
            }
        }
        return true;
    }

    // }}}
    // {{{ _parseSOA()

    /**
     * parses a SOA (Start Of Authority) record line.
     *
     * This function returns the parsed SOA in array form.
     *
     * @param string $line   the SOA line to be parsed.
     *                       Should be stripped of comments and on 1 line.
     * @param string $origin the current origin of this SOA record
     * @param int    $ttl    the TTL of this record
     *
     * @return array array of SOA info to be saved on success,
     *               PEAR error object on failure.
     */
    private function _parseSOA($line, $origin, $ttl)
    {
        $soa = array();
        $regexp = '/(.*) SOA (\S*) (\S*) (\S*) (\S*) (\S*) (\S*) (\S*)/i';
        preg_match($regexp, $line, $matches);
        if (sizeof($matches) != 9) {
            throw new Pear_Exception(
                'Unable to parse SOA.', FILE_DNS_PARSE_SOA_FAILED
            );
        }
        $pre = explode(' ', strtolower($matches[1]));
        if ($pre[0] == '@') {
            $soa['name'] = $origin;
        } else {
            $soa['name'] = $pre[0];
        }
        if (isset($pre[1])) {
            if (strtoupper($pre[1]) == 'IN') {
                $soa['ttl'] = $ttl;
                $soa['class'] = 'IN';
            } else {
                $soa['ttl'] = $this->parseToSeconds($pre[1]);
            }
            if (isset($pre[2])) {
                $soa['class'] = $pre[2];
            }
        } else {
            $soa['ttl'] = $ttl;
            $soa['class'] = 'IN';
        }
        $soa['origin']  = $matches[2];
        $soa['person']  = $matches[3];
        $soa['serial']  = $matches[4];
        $soa['refresh'] = $this->parseToSeconds($matches[5]);
        $soa['retry']   = $this->parseToSeconds($matches[6]);
        $soa['expire']  = $this->parseToSeconds($matches[7]);
        $soa['minimum'] = $this->parseToSeconds($matches[8]);
        return $soa;
    }

    // }}}
    // {{{ _parseRR()

    /**
     * parses a (Resource Record) into an array
     *
     * @param string $line    the RR line to be parsed.
     * @param string $origin  the current origin of this record.
     * @param int    $ttl     the TTL of this record.
     * @param string $current the current domainname we're working on.
     *
     * @return array array of RR info to be saved on success,
     *                PEAR error object on failure.
     */
    private function _parseRR($line, $origin, $ttl, $current)
    {
        $record = array();
        $items = explode(' ', $line);
        $record['name'] = $items[0];
        $record['ttl'] = null;
        $record['class'] = null;
        $record['type'] = null;
        $record['data'] = null;
        if (!$record['name']) {
            //No name specified, inherit current name.
            $record['name'] = $current;
        } elseif ($record['name'] == '@') {
            $record['name'] = $origin;
        }
        if (substr($record['name'], -1) != '.') {
            $record['name'] .= '.' . $origin;
        }
        unset($items[0]);
        foreach ($items as $key => $item) {
            $item = trim($item);
            if (preg_match('/^[0-9]/', $item) 
                && empty($record['ttl'])
            ) {
                //Only a TTL can start with a number.
                $record['ttl'] = $this->parseToSeconds($item);
            } elseif ((strtoupper($item) == 'IN') &&
            is_null($record['class'])) {
                //This is the class definition.
                $record['class'] = 'IN';
            } elseif (array_search($item, $this->_types) &&
            is_null($record['type'])) {
                //We found our type!
                if (is_null($record['ttl'])) {
                    //TTL was left out. Use default.
                    $record['ttl'] = $ttl;
                    $gotTTL = 1;
                }
                if (is_null($record['class'])) {
                    //Class was left out. Use default.
                    $record['class'] = 'IN';
                    $gotClass = 1;
                }
                $record['type'] = $item;
            } elseif (!is_null($record['type'])) {
                //We found out what type we are. This must be the data field.
                switch (strtoupper($record['type'])) {
                case 'A':
                case 'AAAA':
                case 'NS':
                case 'SRV':
                case 'CNAME':
                case 'PTR':
                    $record['data'] = $item;
                    break 2;
                case 'MX':
                    //MX have an extra element. Save both right away.
                    //The setting itself is in the next item.
                    $record['data'] = $items[$key+1];
                    $record['options'] = array('MXPreference' => $item);
                    break 2;
                case 'TXT':
                    $record['data'] .= ' ' . $item;
                    break;
                default:
                    throw new PEAR_Exception(
                        'Unable to parse RR ' . $record['type'] . ' not recognized.',
                        FILE_DNS_PARSE_RR_FAILED
                    );
                }
                //We're done parsing this RR now. Break out of the loop.
            } else {
                throw new PEAR_Exception(
                    'Unable to parse RR. ' . $item . ' not recognized', 
                    FILE_DNS_PARSE_RR_FAILED
                );
            }
        }
        return $record;

    }

    // }}}
    // }}}
    // {{{ free()

    /**
     * resets the object so one can load another file
     *
     * @return bool     true
     */
    public function free()
    {
        $this->_domain = null;
        $this->_filename = null;
        $this->_SOA = array();
        $this->_records = array();
        $this->_isModified = false;
        return true;
    }

    // }}}
    // {{{ Saving
    // {{{ toString()


    /**
     * returns a string with the zonefile generated from this object.
     *
     * @param string $separator The lineending separator. Defaults to \n
     *
     * @throws PEAR_Exception
     * @return string  The generated zone, PEAR Error on failure.
     */
    public function toString($separator = "\n")
    {
        try
        {
            $zone = $this->_generateZone();
        } catch (PEAR_Exception $e) {
            throw $e;
        }
        $zone = implode($separator, $zone);
        return $zone;
    }

    // }}}
    // {{{ save()

    /**
     * saves the zonefile back to the file.
     *
     * @param string $filename  the filename to save to.
     *                            Defaults to the loaded file.
     * @param string $separator the lineending separator.
     *                            Defaults to \n.
     * @param int    $lock      file-lock type to use.
     *                            Defaults to false (none)
     * @param array  $zone      An array zonefile to use instead 
     *
     * @return  true   true on success, PEAR Error on failure.
     */
    public function save(
        $filename = null, $separator = "\n", $lock = false, $zone = null
    ) {
        if (empty($filename)) {
            $filename = $this->_filename;
        }

        if (empty($zone)) {
            try
            {
                $zone = $this->_generateZone();
            } catch (PEAR_Exception $e) {
                throw $e;
            }
        }

        $zone = implode($separator, $zone);

        $save = File::write($filename, $zone, FILE_MODE_WRITE, $lock);

        if (PEAR::isError($save)) {
            //File package doesn't have codes associated with errors,
            //so raise our own.
            throw new PEAR_Exception(
                "Unable to save file $filename", 
                FILE_DNS_FILE_WRITE_FAILED
            );
        }

        return true;
    }

    // }}}
    // {{{ _generateZone()

    /**
     * generates a new zonefile.
     *
     * @return array The generated zonefile, PEAR Error on failure.
     */
    private function _generateZone()
    {
        $zone = array();
        if (!$this->_SOA) {
            throw new PEAR_Exception(
                'Unable to render zone. No zone loaded.', 
                FILE_DNS_RENDER_NOT_LOADED
            );
        }

        $soa = &$this->_SOA;
        if ($this->_isModified) {
            $soa['serial'] = $this->raiseSerial($soa['serial']);
            $this->_isModified = false;
        }
        $tabs = "\t\t\t\t";
        $zone[] = '$ORIGIN ' . $this->_domain . '.';
        $zone[] = implode(
            "\t", array('@', $soa['ttl'], $soa['class'],
            'SOA', $soa['origin'], $soa['person'],
           '('
        )
        );
        $soa['refresh'] = $this->parseFromSeconds($soa['refresh']);
        $soa['retry']   = $this->parseFromSeconds($soa['retry']);
        $soa['expire']  = $this->parseFromSeconds($soa['expire']);
        $soa['minimum'] = $this->parseFromSeconds($soa['minimum']);

        $zone[] = $tabs . $soa['serial']  .    "\t; serial";
        $zone[] = $tabs . $soa['refresh'] .  "\t\t; refresh";
        $zone[] = $tabs . $soa['retry']   .  "\t\t; retry";
        $zone[] = $tabs . $soa['expire']  .  "\t\t; expire";
        $zone[] = $tabs . $soa['minimum'] . ")\t\t; minimum";
        $zone[] = '';

        foreach ($this->getGenerates() as $generate) {
            $zone[] = '$GENERATE ' . $generate;
        }
        $zone[] = '';

        foreach ($this->getRecords() as $record) {
            $record['ttl'] = $this->parseFromSeconds($record['ttl']);

            switch (strtoupper($record['type'])) {
            case 'MX':
                //MX have an extra element.
                //The setting itself is in the next item.
                $zone[] = implode(
                    "\t", array(
                        $record['name'],
                        $record['ttl'],
                        $record['class'],
                        $record['type'],
                        $record['options']['MXPreference'],
                        $record['data']
                    )
                );
                break;

            case 'A':
            case 'AAAA':
            case 'NS':
            case 'CNAME':
            case 'PTR':
            case 'TXT':
            default:
                $zone[] = implode("\t", $record);
                break;
            }
        }

        $zone[] = '';
        return $zone;
    }

    // }}}
    // }}}
    // {{{ Modifiers
    /**
    * Adds a record to the currently loaded zone.
    *
    * This function returns the new record in array form.
    *
    * @param string $name  the SOA line to be parsed.
    *                      Should be stripped of comments and on 1 line.
    * @param int    $ttl   the TTL of this record
    * @param string $class The Class of the record, normally 'IN'
    * @param string $type  The Type of record, defaults to 'A'
    * @param string $data  The data to be passed saved in the record
    *
    * @return array 	   Record array or false on failure
    */
    public function addRecord(
        $name = null, $ttl = null, $class = 'IN', $type = 'A', $data = '127.0.0.1'
    ) {
        $record['name'] = $name;
        $record['ttl'] = $ttl;
        $record['class'] = $class;
        $record['type'] = $type;
        $record['data'] = $data;

        if (empty($record['name']) || $record['name'] == '@') {
            $record['name'] = $this->_SOA['origin'];
        }

        if (empty($record['ttl'])) {
            $record['ttl'] = $this->_SOA['ttl'];
        }

        if (empty($record['class'])) {
            $record['class'] = 'IN';
        }

        if (empty($record['type'])) {
            throw new PEAR_Exception(
                'Unable to add record. type cannot be empty.',
                FILE_DNS_PARSE_RR_FAILED
            );
        }

        if (empty($record['data'])) {
            throw new PEAR_Exception(
                'Unable to add record. data cannot be empty.',
                FILE_DNS_PARSE_RR_FAILED
            );
        }

        $_records[] = $record;
        return $record;
    }
    // }}}
    // {{{ Getters
    // {{{ getSOA()

    /**
     * Gets the SOA section of the currently loaded zone.
     *
     * @return Array
     */
    public function getSOA()
    {
        if (!empty($this->_SOA) && is_array($this->_SOA)) {
            return $this->_SOA;
        }
        return Array();
    }
    // }}}
    // {{{ getRecords()

    /**
     * Gets the Records array of the currently loaded zone.
     *
     * @return Array
     */
    public function getRecords()
    {
        if (!empty($this->_records) && is_array($this->_records)) {
            return $this->_records;
        }
        return Array();
    }
    // }}}
    // {{{ getGenerates()

    /**
     * Gets the Records array of the currently loaded zone.
     *
     * @return Array
     */
    public function getGenerates()
    {
        if (!empty($this->_generate) && is_array($this->_generate)) {
            return $this->_generate;
        } else {
            return Array();
        }
    }
    // }}}
    // {{{ Setters
    // {{{ setDomainName()

    /**
     * sets the domain name of the currently loaded zone.
     * It also handles changing all the RR's already saved.
     *
     * @param string $domain  the new domain name
     * @param bool   $migrate whether or not to change all occurances
     *                           of *.oldomain
     *                           to the new domain name.
     *                           Defaults to true.
     *
     * @throws PEAR_Exception
     * @return bool  true on success, PEAR Error on failure.
     */
    public function setDomainName($domain, $migrate = true)
    {
        $valid = '/^[A-Za-z0-9\-\_\.]*$/';
        if (!preg_match($valid, $domain)) {
            throw new PEAR_Exception(
                'Unable to set domainname. ' . $domain,
                FILE_DNS_INVALID_DOMAIN
            );
        }
        $oldDomain = $this->_domain;
        $domain = rtrim($domain, '.');
        $this->_domain = $domain;
        if ($this->_SOA) {
            $this->_isModified = true;
            if ($migrate) {
                $search = '/^(.*)(' . preg_quote($oldDomain) . ')(\.)$/';
                $replace = '$1' . $domain . '$3';

                $this->_SOA['name'] = preg_replace(
                    $search, $replace, $this->_SOA['name']
                );

                $this->_SOA['origin'] = preg_replace(
                    $search, $replace, $this->_SOA['origin']
                );

                $this->_SOA['person'] = preg_replace(
                    $search, $replace, $this->_SOA['person']
                );

                foreach ($this->_records as $key => $record) {
                    $this->_records[$key]['name'] = preg_replace(
                        $search, $replace, $this->_records[$key]['name']
                    );
                    $this->_records[$key]['data'] = preg_replace(
                        $search, $replace, $this->_records[$key]['data']
                    );
                }
            }
        }
        return true;
    }

    // }}}
    // {{{ setSOAValue()

    /**
     * sets a specific value in the SOA field.
     *
     * This function updates the list of SOA data we have.
     * List of accepted key => value pairs:
     * <pre>
     * Array
     *   (
     *       [name] => example.com.
     *       [ttl] => 345600
     *       [class] => IN
     *       [origin] => ns1.example.com.
     *       [person] => hostmaster.example.com.
     *       [serial] => 204041514
     *       [refresh] => 14400
     *       [retry] => 1800
     *       [expire] => 86400
     *       [minimum] => 10800
     *   )
     * </pre>
     *
     * @param array $values A list of key -> value pairs
     *
     * @return bool  true on success, PEAR Error on failure.
     * @see _SOA
     */
    public function setSOAValue($values)
    {
        $soa = array();
        if (!is_array($values)) {
            throw new PEAR_Exception(
                'Unable to set SOA value. ', FILE_DNS_UPDATE_SOA_FAILED
            );
        }
        $validKeys = array('name', 'ttl', 'class', 'origin', 'person',
                           'serial', 'refresh', 'retry', 'expire', 'minimum');
        foreach ($values as $key => $value) {
            if (array_search($key, $validKeys) === false) {
                throw new PEAR_Exception(
                    'Unable to set SOA value. ' . $key . ' not recognized ',
                    FILE_DNS_UPDATE_SOA_FAILED
                );
            }

            switch (strtolower($key)) {
            case 'person':
                $value = str_replace('@', '.', $value);
                $value = trim($value, '.') . '.';
            case 'name':
            case 'origin':
                $valid = '/^[A-Za-z0-9\-\_\.]*\.$/';
                if (preg_match($valid, $value)) {
                    $soa[$key] = $value;
                } else {
                    throw new PEAR_Exception(
                        'Unable to set SOA value. ' . $key . ' not valid ',
                        FILE_DNS_UPDATE_SOA_FAILED
                    );
                }
                break;
            case 'class':
                $soa[$key] = $value;
                break;
            case 'ttl':
            case 'serial':
            case 'refresh':
            case 'retry':
            case 'expire':
            case 'minimum':
                if (is_numeric($value)) {
                    $soa[$key] = $value;
                } else {
                    throw new PEAR_Exception(
                        'Unable to set SOA value. ' . $key . ' not recognized',
                        FILE_DNS_UPDATE_SOA_FAILED
                    );
                }
                break;
            }


        }
        //If all got parsed, save values.
        $this->_SOA = array_merge($this->_SOA, $soa);
        return true;
    }

    // }}}
    // {{{ setTTL()

    /**
     * sets the TTL of a specific, or not so specific, record.
     *
     * @param int    $new  The new TTL for this record
     * @param string $name The name of the record to edit. (null for all)
     * @param string $type The type of the record to edit. (null for all)
     * @param string $data The data of the record to edit. (null for all)
     *
     * @return bool   true.
     */
    public function setTTL($new, $name = null, $type = null, $data = null)
    {
        $new = abs(intval($new));
        foreach ($this->_records as $key => $record) {
            if ((empty($name)
                || (0 === strcasecmp($name, $record['name']))
                || (0 == strcasecmp($name. $this->_domain . '.', $record['name'])))
                && ((empty($type))
                || (0 == strcasecmp($type, $record['type'])))
                && ((empty($data))
                || (0 == strcasecmp($data, $record['data'])))
            ) {
                $this->_records[$key]['ttl'] = $new;
            }
        }
        return true;
    }

    // }}}
    // {{{ setName()

    /**
     * sets the name of a specific, or not so specific, record.
     *
     * @param string $new  The new name for this record. If needed, the
     *                      current domainname will be automaticly appended.
     * @param string $name The name of the record to edit. (null for all)
     * @param string $type The type of the record to edit. (null for all)
     * @param string $data The data of the record to edit. (null for all)
     *
     * @return bool   true.
     */
    public function setName($new, $name = null, $type = null, $data = null)
    {
        $new = strval($new);
        $quotedDomain = preg_quote($this->_domain);
        if (substr($new, -1) == '.') {
            //String already correct.
        } elseif (preg_match("/$quotedDomain" . '$/i', $new)) {
            //String ends with this domain. Append a .
            $new .= '.';
        } else {
            //Subdomain specified. Append domainname
            $new .= '.' . $this->_domain . '.';
        }
        foreach ($this->_records as $key => $record) {
            if ($this->compareValues($name, $type, $data, $record)) {
                $this->_records[$key]['name'] = $new;
            }
        }
        return true;
    }

    // }}}
    // {{{ setData()

    /**
     * sets the Value of a specific, or not so specific, record.
     *
     * @param string $new  The new Value for this record
     * @param string $name The name of the record to edit. (null for all)
     * @param string $type The type of the record to edit. (null for all)
     * @param string $data The data of the record to edit. (null for all)
     *
     * @return bool   true on success, PEAR_ERROR on error.
     *
     */
    public function setValue($new, $name = null, $type = null, $data = null)
    {
        $new = strval($new);
        foreach ($this->_records as $key => $record) {
            if ($this->_compareValues($name, $type, $data, $record)) {
                $this->_records[$key]['data'] = $new;
            }
        }
        return true;
    }

    // }}}
    // {{{ setMXPref()

    /**
     * sets the MX Preference of an MX record
     * to the specified value.
     *
     * @param int    $pref   the preference level.
     * @param string $server the mailserver this MX points to.   (null for all)
     * @param string $name   the (sub)domain this MX applies to. (null for all)
     *
     * @return bool  true on success, PEAR Error on failure.
     */
    public function setMXPref($pref, $server = null, $name = null )
    {
        $pref = intval($pref);
        $quotedDomain = preg_quote($this->_domain);
        if ($name === null) {
            //null filled in, leave it like that.
        } elseif (!$name) {
            //name string left empty? Set to null
            $name = null;
        } elseif (substr($name, -1) == '.') {
            //String already correct.
        } elseif (preg_match("/$quotedDomain" . '$/i', $name)) {
            //String ends with this domain. Append a .
            $name .= '.';
        } else {
            //Subdomain specified. Append domainname
            $name .= '.' . $this->_domain . '.';
        }
        if ($server === null) {
            //null filled in, leave it like that.
        } elseif (!$server) {
            //Server string left empty? Set to null
            $server = null;
        } elseif (substr($server, -1) == '.') {
            //String already correct.
        } elseif (preg_match("/$quotedDomain" . '$/i', $server)) {
            //String ends with this domain. Append a .
            $server .= '.';
        } else {
            //Subdomain specified. Append domainname
            $server .= '.' . $this->_domain . '.';
        }
        foreach ($this->_records as $key => $record) {
            if (($record['type'] == 'MX')
                && (empty($server) || ($server == $record['data']))
                && ((empty($name)) || ($name   == $record['name']) )
            ) {
                if (!isset($this->_records[$key]['options'])) {
                    $this->_records[$key]['options'] = array();
                }
                $this->_records[$key]['options']['MXPreference'] = $pref;
            }
        }
        return true;
    }

    // }}}
    // }}}
    // {{{ Static functions
    // {{{ Compare Values
    /**
     * Checks to see if values are empty or match the existing value
     *
     * @param string $name   Name value to check
     * @param string $type   Type value to check
     * @param string $data   Data value to check
     * @param array  $record The record to compare against
     *
     * @return bool returns true if they do not match and are not empty
     */
    private function _compareValues($name, $type, $data, $record)
    {
        if (!empty($name)
            && ((strcasecmp($name, $record['name']) !== 0) 
            ||  (strcasecmp($name . $this->_domain, $record['name']) !== 0))
        ) {
            return true;
        }

        if (!empty($type)
            && (strcasecmp($type, $record['type']) !== 0)
        ) { 
            return true;
        }

        if (!empty($data)
            || (strcasecmp($data, $record['data']) !== 0)
        ) {
            return true;
        }

        return false;
    }
    // }}}
    // {{{ raiseSerial()

    /**
     * generate a new serial based on given one.
     *
     * This generates a new serial, based on the often used format
     * YYYYMMDDXX where XX is an ascending serial,
     * allowing up to 100 edits per day. After that the serial wraps
     * into the next day and it still works.
     *
     * @param int $serial Current serial
     *
     * @static
     * @return int New serial
     */
    public function raiseSerial($serial=0)
    {
        if (substr($serial, 0, 8) == date('Ymd')) {
            //Serial's today. Simply raise it.
            $serial = $serial + 1;
        } elseif ($serial > date('Ymd00')) {
            //Serial's after today.
            $serial = $serial + 1;
        } else {
            //Older serial. Generate new one.
            $serial = date('Ymd00');
        }
        return intval($serial);
    }

    // }}}
    // {{{ parseToSeconds()

    /**
     * converts a BIND-style timeout(1D, 2H, 15M) to seconds.
     *
     * @param string $time Time to convert.
     *
     * @static
     * @return int    time in seconds on success, PEAR error on failure.
     */
    public static function parseToSeconds($time)
    {
        if (is_numeric($time)) {
            //Already a number. Return.
            return $time;
        } else {
            $pattern = '/([0-9]+)([a-zA-Z]+)/';
            $split = preg_split(
                $pattern, $time, -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
            );
            if (count($split) != 2) {
                throw new PEAR_Exception(
                    "Unable to parse time. $time", FILE_DNS_PARSE_TIME_FAILED
                );
            }
            list($num, $what) = $split;
            switch (strtoupper($what))
            {
            case 'S':
                $times = 1; //Seconds
                break;
            case 'M':
                $times = 1 * 60; //Minute
                break;
            case 'H':
                $times = 1 * 60 * 60; //Hour
                break;
            case 'D':
                $times = 1 * 60 * 60 * 24; //Day
                break;
            case 'W':
                $times = 1 * 60 * 60 * 24 * 7; //Week
                break;
            default:
                throw new PEAR_Exception(
                    "Unable to parse time. $time", 
                    FILE_DNS_PARSE_TIME_FAILED
                );
                break;
            }
            $time = $num * $times;
            return $time;
        }
    }

    // }}}
    // {{{ parseFromSeconds()

    /**
     * converts seconds to BIND-style timeout(1D, 2H, 15M).
     *
     * @param int $ttl seconds to convert
     *
     * @static
     * @return string String with time on success, PEAR error on failure.
     *
     */
    public static function parseFromSeconds($ttl)
    {
        $ttl = intval($ttl);
        if (!is_int($ttl)) {
            throw new PEAR_Exception(
                "Unable to parse time back. $ttl",
                FILE_DNS_PARSEBACK_TIME_FAILED
            );
        } elseif (is_int($num = ($ttl / ( 1 * 60 * 60 * 24 * 7)))) {
            return "$num" . 'W';
        } elseif (is_int($num = ($ttl / ( 1 * 60 * 60 * 24)))) {
            return "$num" . 'D';
        } elseif (is_int($num = ($ttl / ( 1 * 60 * 60)))) {
            return "$num" . 'H';
        } elseif (is_int($num = ($ttl / ( 1 * 60)))) {
            return "$num" . 'M';
        } elseif (is_int($num = ($ttl / ( 1)))) {
            return "$num";
        }
    }

    // }}}
    // {{{ isIP()


    /**
     * checks if a value is an IP address or not.
     *
     * @param string $value Value to check.
     *
     * @static
     * @return bool  true or false.
     *
     */
    public static function isIP($value)
    {
        // http://www.regular-expressions.info/regexbuddy/ipaccurate.html
        $ipaccurate = '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}'.
        '(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';
        return preg_match($ipaccurate, $value);
    }

    // }}}
    // {{{ apiVersion()

    /**
     * returns the API version
     *
     * @return int      The API version number
     * @static
     * @access public
     */
    public static function apiVersion()
    {
        return '0.1.1';
    }

    // }}}
    // }}}

}
// }}}

<?php
/**
 * /classes/DomainMOD/GoDaddy.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

class DirectNIC
{
    public $format;
    public $log;

    private $csvFile = '/docs/DirectNIC-Import.csv';

    private static $list = null;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('directnic.class');
    }

    public function loadCsv(){

        $this->log->debug('Loading list csv...');

        $file = fopen($_SERVER['DOCUMENT_ROOT'].$this->csvFile, 'r');

        $result = array();
        $keys = null;

        while (($data = fgetcsv($file)) !== false) {
            if ($keys==null) {
                $keys = $data;
            } else {
                $data = array_combine($keys, $data);
                $result[$data['domain_name']] = $data;
            }
        }

        if (sizeof($result)>0) {
            $this->log->debug("csv loaded!");
            self::$list = $result;
            return true;
        }

        return false;
    }

    public function getDomainList()
    {


        $domain_list = array();
        $domain_count = 0;
        $result = false;

        if (self::$list==null){
            $result = $this->loadCsv();
        }

        // confirm that the api call was successful
        if ($result) {

            foreach (self::$list as $domain) {

                $domain_list[] = $domain['domain_name'];
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $this->log->error($log_message);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $this->log->debug('loading domain details for: '.$domain);

        if (self::$list==null){
            $this->loadCsv();
        }

        // confirm that the api call was successful
        if (isset(self::$list[$domain])) {

            $domainDetails = self::$list[$domain];

            // get expiration date
            if ($domainDetails['exdate']!="") {
                $expiration_date = explode("/", $domainDetails['exdate']);
                $expiration_date = mktime(0, 0, 0, $expiration_date[0], $expiration_date[1], $expiration_date[2]);
                $expiration_date = date("Y-m-d", $expiration_date);
            } else {
                $expiration_date = "1978-01-23";
            }

            // get dns servers
            $dns_result = $domainDetails['nameservers'];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_result = (string) $domainDetails['privacy'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = (string) $domainDetails['auto_renew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain);
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty($dns_result)) {
            $dns_servers = explode("|", $dns_result);
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'Off') {
            $privacy_status = '0';
        } else {
            $privacy_status = '1';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'Off') {
            $autorenewal_status = '0';
        } else {
            $autorenewal_status = '1';
        }
        return $autorenewal_status;
    }

} //@formatter:on

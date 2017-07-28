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

class EuroDns
{
    public $format;
    public $log;

    private $endpoint = 'https://secure.api-eurodns.com:20015/v2/index.php';

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('eurodns.class');
    }

    public function getApiRequestXml($command, $domain=null)
    {
        $xml = new \SimpleXMLElement('<request xmlns:domain="http://www.eurodns.com/domain"/>');
        if ($command == 'domainlist') {
            $xml->addChild('domain:list', null, 'domain');
        } elseif ($command == 'info') {
            $info = $xml->addChild('domain:info', null, 'domain');
            $info->addChild('domain:name', $domain, 'domain');
        } else {
            return 'Unknown command '.$command;
        }
        return $xml->asXML();
    }

    public function apiCall($api_key, $api_secret, $xml)
    {

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 'Content-Type: application/x-www-form-urlencoded; charset=utf-8');
        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ":MD5" . md5($api_secret));
        curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=".urlencode($xml));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $this->log->debug('Sending request to EuroDNS');

        $response = curl_exec($ch);

        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($responseCode=='200'){

            $xml = new \SimpleXMLElement($response, 0, false, 'domain');

            $resultCode = $xml->children()->attributes()->code;

            if ($resultCode==1000)
                return $xml;

            $this->log->debug("API Error: ".$resultCode);
        } else {
            $this->log->debug("API HTTP Error: ".$responseCode);
        }

        return null;
    }

    public function getDomainList($api_key, $api_secret)
    {
        $domain_list = array();
        $domain_count = 0;

        $this->log->debug("loading domain list");

        $xml = $this->getApiRequestXml('domainlist');
        $xml = $this->apiCall($api_key, $api_secret, $xml);

        // confirm that the api call was successful
        if ($xml!=null && sizeof($children = $xml->xpath("//domain:name"))>0) {

            foreach ($children as $domain) {
                $domain_list[] = $domain;
                $domain_count++;
            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Username' => $this->format->obfusc($api_key), 'API Secret' => $this->format->obfusc($api_secret));
            $this->log->error($log_message, $log_extra);
        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $api_secret, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $xml = $this->getApiRequestXml('info', $domain);
        $xml = $this->apiCall($api_key, $api_secret, $xml);

        $this->log->debug("Domain: ".$domain);

        // confirm that the api call was successful
        if ($xml!=null) {

            // get expiration date
            $expiration_date = substr($xml->xpath("//domain:expDate")[0], 0, 10);

            // get dns servers
            $dns_servers = $this->processDns($xml->xpath("//domain:ns"));

            // get privacy status
            $privacy_result = (string) $xml->xpath("//service:domainprivacy")[0];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = (string) $xml->xpath("//domain:renewal")[0];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);


            $this->log->debug($expiration_date);
            $this->log->debug(json_encode($dns_servers));
            $this->log->debug($privacy_status);
            $this->log->debug($autorenewal_status);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'API Username' => $api_key, 'API Secret' => $this->format->obfusc($api_secret));
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function processDns($dns_result)
    {
        if (!empty($dns_result)) {
            $dns_servers = array();
            foreach ($dns_result as $ns){
                $dns_servers[] = (string) $ns;
            }
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'No') {
            $privacy_status = '0';
        } else {
            $privacy_status = '1';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'autoRenew') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on

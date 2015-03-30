<?php

class OC_Chooser {

    const TRUSTED_NET = '10.2.';
    const COMPUTE_IP = '10.2.0.1';
    const IPS_TTL_SECONDS = 30;
    const IPS_CACHE_KEY = 'compute_ips';

    public static function checkIP(){
        OC_Log::write('chooser', 'Client IP '.$_SERVER['REMOTE_ADDR'], OC_Log::DEBUG);
        if(strpos($_SERVER['REMOTE_ADDR'], OC_Chooser::TRUSTED_NET) !== 0){
            return "";
        }
        $user_id = '';
        if(($list_array = apc_fetch(OC_Chooser::IPS_CACHE_KEY)) === false){
            $list_line = file_get_contents("https://".OC_Chooser::COMPUTE_IP."/steamengine/networks?f=1&action=tablelist");
            $list_array = explode("\n", $list_line);
            apc_add(OC_Chooser::IPS_CACHE_KEY, $list_array, OC_Chooser::IPS_TTL_SECONDS);
            OC_Log::write('chooser', 'Refreshed IP cache: '.$list_array[3], OC_Log::INFO);
        }
        foreach($list_array as $line){
            $entries = explode("|", $line);
            if(count($entries)<8){
                continue;
            }
            $ip = trim($entries[5]);
            $owner = trim($entries[7]);
            if($ip != '' && $_SERVER['REMOTE_ADDR'] == $ip && $owner != ''){
                OC_Log::write('chooser', 'CHECK IP: '.$ip.":".$owner, OC_Log::INFO);
                $user_id = $owner;
                \OC::$session->set('user_id', $owner);
                break;
            }
        }
        OC_Log::write('chooser', 'user_id: '.$user_id, OC_Log::DEBUG);
        return $user_id;
    }

    public static function getEnabled() {
        return OCP\Config::getUserValue(OCP\USER::getUser(), 'chooser', 'allow_internal_dav', 'no');
    }

    /* $value: 'yes' og 'no'*/
    public static function setEnabled($value) {
        if($value != 'yes' && $value != 'no'){
            throw new \Exception("Must be yes or no: $value");
        }
        OCP\Config::setUserValue(OCP\USER::getUser(), 'chooser', 'allow_internal_dav', $value);
    }

}

<?php
namespace GrinJackal\LGSQ\Protocols;

class Query34 extends Query33{
    public static function Query34(&$server, &$lgsl_need, &$lgsl_fp) // Rage:MP
    {
        if(!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, 'https://cdn.rage.mp/master/');
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(isset($buffer[$server['basic']['ip'].':'.$server['basic']['c_port']])){
            $value = $buffer[$server['basic']['ip'].':'.$server['basic']['c_port']];
            $server['server']['name']           = $value['name'];
            $server['server']['map']            = "ragemp";
            $server['server']['players']        = $value['players'];
            $server['server']['playersmax']     = $value['maxplayers'];
            $server['convars']['url']           = $value['url'];
            $server['convars']['peak']          = $value['peak'];
            $server['convars']['gamemode']      = $value['gamemode'];
            $server['convars']['lang']          = $value['lang'];
            return TRUE;
        }
        return FALSE;
    }

}
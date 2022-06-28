<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query35 extends Query34{
    public static function Query35(&$server, &$lgsl_need, &$lgsl_fp) // FiveM / RedM
    {
        if(!$lgsl_fp) return FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/dynamic.json");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(!$buffer) return FALSE;

        $server['server']['name']           = Functions::ParserColor($buffer['hostname'], 'fivem');
        $server['server']['players']        = $buffer['clients'];
        $server['server']['playersmax']     = $buffer['sv_maxclients'];
        $server['server']['map'] = $buffer['mapname'];

        if ($server['server']['map'] == 'redm-map-one'){
            $server['server']['game'] = 'redm';
        }

        $server['convars']['gametype']      = $buffer['gametype'];
        $server['convars']['version']       = $buffer['iv'];

        if($lgsl_need['p']) {
            $lgsl_need['p'] = FALSE;

            curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/players.json");
            $buffer = curl_exec($lgsl_fp);
            $buffer = json_decode($buffer, true);

            foreach($buffer as $key => $value){
                $server['players'][$key]['name'] = $value['name'];
                $server['players'][$key]['ping'] = $value['ping'];
            }
        }
        return TRUE;
    }
}
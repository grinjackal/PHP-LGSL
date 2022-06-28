<?php
namespace GrinJackal\LGSQ\Protocols;

class Query38 extends Query37{
    public static function Query38(&$server, &$lgsl_need, &$lgsl_fp) // Terraria
    {
        if (!$lgsl_fp) return FALSE;
    
        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/v2/server/status?players=true");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if($buffer['status'] != '200'){
            $server['convars']['_error']    = $buffer['error'];
            return FALSE;
        }
    
        $server['server']['name']           = $buffer['name'];
        $server['server']['map']            = $buffer['world'];
        $server['server']['players']        = $buffer['playercount'];
        $server['server']['playersmax']     = $buffer['maxplayers'];
        $server['server']['password']       = $buffer['serverpassword'];
        $server['convars']['uptime']        = $buffer['uptime'];
        $server['convars']['version']       = $buffer['serverversion'];

        return TRUE;
    }
}
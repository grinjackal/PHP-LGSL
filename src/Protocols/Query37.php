<?php
namespace GrinJackal\LGSQ\Protocols;

class Query37 extends Query36{
    public static function Query37(&$server, &$lgsl_need, &$lgsl_fp) // SCUM API
    {
        if (!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "https://api.hellbz.de/scum/api.php?address={$server['basic']['ip']}&port={$server['basic']['c_port']}");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(!$buffer['success']){ return FALSE; }

        $lgsl_need['s'] = FALSE;

        $server['server']['name']           = $buffer['data'][0]['name'];
        $server['server']['map']            = "SCUM";
        $server['server']['players']        = $buffer['data'][0]['players'];
        $server['server']['playersmax']     = $buffer['data'][0]['players_max'];
        $server['convars']['time']          = $buffer['data'][0]['time'];
        $server['convars']['version']       = $buffer['data'][0]['version'];

        return TRUE;
    }
}
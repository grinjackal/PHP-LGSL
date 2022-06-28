<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query07 extends Query06{
    public static function Query07(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFstatus\x00");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $buffer = substr($buffer, 6, -2); // REMOVE HEADER AND FOOTER
        $part   = explode("\n", $buffer); // SPLIT INTO SETTINGS/PLAYER/PLAYER/PLAYER

        //---------------------------------------------------------+
        $item = explode("\\", $part[0]);

        foreach ($item as $item_key => $data_key)
        {
            if ($item_key % 2) { continue; } // SKIP ODD KEYS

            $data_key               = strtolower($data_key);
            $server['convars'][$data_key] = $item[$item_key+1];
        }

        //---------------------------------------------------------+
        array_shift($part); // REMOVE SETTINGS

        foreach ($part as $key => $data)
        {
            preg_match("/(.*) (.*) (.*) (.*) \"(.*)\" \"(.*)\" (.*) (.*)/s", $data, $match); // GREEDY MATCH FOR SKINS

            $server['players'][$key]['pid']         = $match[1];
            $server['players'][$key]['score']       = $match[2];
            $server['players'][$key]['time']        = $match[3];
            $server['players'][$key]['ping']        = $match[4];
            $server['players'][$key]['name']        = Functions::ParserColor($match[5], $server['basic']['type']);
            $server['players'][$key]['skin']        = $match[6];
            $server['players'][$key]['skin_top']    = $match[7];
            $server['players'][$key]['skin_bottom'] = $match[8];
        }

        //---------------------------------------------------------+
        $server['server']['game']       = $server['convars']['*gamedir'];
        $server['server']['name']       = $server['convars']['hostname'];
        $server['server']['map']        = $server['convars']['map'];
        $server['server']['players']    = $server['players'] ? count($server['players']) : 0;
        $server['server']['playersmax'] = $server['convars']['maxclients'];
        $server['server']['password']   = isset($server['convars']['needpass']) && $server['convars']['needpass'] > 0 && $server['convars']['needpass'] < 4 ? 1 : 0;

        //---------------------------------------------------------+
        return TRUE;
    }
}
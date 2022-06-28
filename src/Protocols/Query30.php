<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query30 extends Query29{
    public static function Query30(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://blogs.battlefield.ea.com/battlefield_bad_company/archive/2010/02/05/remote-administration-interface-for-bfbc2-pc.aspx
        //  THIS USES TCP COMMUNICATION
        if ($lgsl_need['s'] || $lgsl_need['c']){
            fwrite($lgsl_fp, "\x00\x00\x00\x00\x1B\x00\x00\x00\x01\x00\x00\x00\x0A\x00\x00\x00serverInfo\x00");
        }
        elseif ($lgsl_need['p']){
          fwrite($lgsl_fp, "\x00\x00\x00\x00\x24\x00\x00\x00\x02\x00\x00\x00\x0B\x00\x00\x00listPlayers\x00\x03\x00\x00\x00all\x00");
        }

        //---------------------------------------------------------+
        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $length = Functions::UnPack(substr($buffer, 4, 4), "L");

        while (strlen($buffer) < $length){
            $packet = fread($lgsl_fp, 4096);
            if ($packet) { $buffer .= $packet; } else { break; }
        }

        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER

        $response_type = Functions::CutPascal($buffer, 4, 0, 1);

        if ($response_type != "OK") { return FALSE; }

        //---------------------------------------------------------+

        if ($lgsl_need['s'] || $lgsl_need['c'])
        {
            $lgsl_need['s'] = FALSE;
            $lgsl_need['c'] = FALSE;

            $server['server']['name']               = Functions::CutPascal($buffer, 4, 0, 1);
            $server['server']['players']            = Functions::CutPascal($buffer, 4, 0, 1);
            $server['server']['playersmax']         = Functions::CutPascal($buffer, 4, 0, 1);
            $server['convars']['gamemode']          = Functions::CutPascal($buffer, 4, 0, 1);
            $server['server']['map']                = Functions::CutPascal($buffer, 4, 0, 1);
            $server['convars']['score_attackers']   = Functions::CutPascal($buffer, 4, 0, 1);
            $server['convars']['score_defenders']   = Functions::CutPascal($buffer, 4, 0, 1);

            // CONVERT MAP NUMBER TO DESCRIPTIVE NAME
            $server['convars']['level'] = $server['server']['map'];
            $map_check = strtolower($server['server']['map']);

            if     (strpos($map_check, "mp_001") !== FALSE) { $server['server']['map'] = "Panama Canal";   }
            elseif (strpos($map_check, "mp_002") !== FALSE) { $server['server']['map'] = "Valparaiso";     }
            elseif (strpos($map_check, "mp_003") !== FALSE) { $server['server']['map'] = "Laguna Alta";    }
            elseif (strpos($map_check, "mp_004") !== FALSE) { $server['server']['map'] = "Isla Inocentes"; }
            elseif (strpos($map_check, "mp_005") !== FALSE) { $server['server']['map'] = "Atacama Desert"; }
            elseif (strpos($map_check, "mp_006") !== FALSE) { $server['server']['map'] = "Arica Harbor";   }
            elseif (strpos($map_check, "mp_007") !== FALSE) { $server['server']['map'] = "White Pass";     }
            elseif (strpos($map_check, "mp_008") !== FALSE) { $server['server']['map'] = "Nelson Bay";     }
            elseif (strpos($map_check, "mp_009") !== FALSE) { $server['server']['map'] = "Laguna Presa";   }
            elseif (strpos($map_check, "mp_012") !== FALSE) { $server['server']['map'] = "Port Valdez";    }
        }

        //---------------------------------------------------------+
        elseif ($lgsl_need['p'])
        {
            $lgsl_need['p'] = FALSE;

            $field_total = Functions::CutPascal($buffer, 4, 0, 1);
            $field_list  = array();

            for ($i = 0; $i < $field_total; $i++)
            {
              $field_list[] = strtolower(Functions::CutPascal($buffer, 4, 0, 1));
            }

            $player_squad = ["","Alpha","Bravo","Charlie","Delta","Echo","Foxtrot","Golf","Hotel"];
            $player_team  = ["","Attackers","Defenders"];
            $player_total = Functions::CutPascal($buffer, 4, 0, 1);

            for ($i = 0; $i < $player_total; $i++){
                foreach ($field_list as $field){
                    $value = Functions::CutPascal($buffer, 4, 0, 1);
                    switch ($field)
                    {
                        case "clantag": $server['players'][$i]['name']  = $value;                                                                             break;
                        case "name":    $server['players'][$i]['name']  = empty($server['players'][$i]['name']) ? $value : "[{$server['players'][$i]['name']}] {$value}"; break;
                        case "teamid":  $server['players'][$i]['team']  = isset($player_team[$value]) ? $player_team[$value] : $value;                        break;
                        case "squadid": $server['players'][$i]['squad'] = isset($player_squad[$value]) ? $player_squad[$value] : $value;                      break;
                        default:        $server['players'][$i][$field]  = $value;                                                                             break;
                    }
                }
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
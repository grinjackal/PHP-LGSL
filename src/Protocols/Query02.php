<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query02 extends Query01{
    public static function Query02(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if($server['basic']['type'] == "quake2"){
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFstatus");
        }
        elseif ($server['basic']['type'] == "warsowold"){
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFgetinfo");
        }
        elseif(strpos($server['basic']['type'], "moh") !== FALSE){ 
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x02getstatus");
        }else{ 
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFgetstatus");
        }

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $part = explode("\n", $buffer);  // SPLIT INTO PARTS: HEADER/SETTINGS/PLAYERS/FOOTER
        array_pop($part);                // REMOVE FOOTER WHICH IS EITHER NULL OR "\challenge\"
        $item = explode("\\", $part[1]); // SPLIT PART INTO ITEMS

        foreach ($item as $item_key => $data_key)
        {
            if (!($item_key % 2)) { 
                continue; 
            } // SKIP EVEN KEYS

            $data_key                       = strtolower(Functions::ParserColor($data_key, "1"));
            $server['convars'][$data_key]   = Functions::ParserColor($item[$item_key+1], "1");
        }

        //---------------------------------------------------------+
        if (!empty($server['convars']['hostname']))    { $server['server']['name'] = $server['convars']['hostname']; }
        if (!empty($server['convars']['sv_hostname'])) { $server['server']['name'] = $server['convars']['sv_hostname']; }

        if (isset($server['convars']['gamename'])) { $server['server']['game'] = $server['convars']['gamename']; }
        if (isset($server['convars']['mapname']))  { $server['server']['map']  = $server['convars']['mapname']; }

        $server['server']['players'] = empty($part['2']) ? 0 : count($part) - 2;

        if (isset($server['convars']['maxclients']))    { $server['server']['playersmax'] = $server['convars']['maxclients']; }    // QUAKE 2
        if (isset($server['convars']['sv_maxclients'])) { $server['server']['playersmax'] = $server['convars']['sv_maxclients']; }

        if (isset($server['convars']['pswrd']))      { $server['server']['password'] = $server['convars']['pswrd']; }              // CALL OF DUTY
        if (isset($server['convars']['needpass']))   { $server['server']['password'] = $server['convars']['needpass']; }           // QUAKE 2
        if (isset($server['convars']['g_needpass'])) { $server['server']['password'] = (int)$server['convars']['g_needpass']; }

        array_shift($part); // REMOVE HEADER
        array_shift($part); // REMOVE SETTING

        //---------------------------------------------------------+
        // (SCORE) (PING) (TEAM IF TEAM GAME) "(NAME)"
        if ($server['basic']['type'] == "nexuiz")
        {
            $pattern = "/(.*) (.*) (.*)\"(.*)\"/U"; 
            $fields = [ 1 => "score", 2 => "ping", 3 => "team", 4 => "name" ];
        }
        // (SCORE) (PING) "(NAME)" (TEAM)
        elseif ($server['basic']['type'] == "warsow")
        {
            $pattern = "/(.*) (.*) \"(.*)\" (.*)/"; 
            $fields = [ 1 => "score", 2 => "ping", 3 => "name", 4 => "team" ];
        }
        // (SCORE) (PING) (DEATHS) "(NAME)"
        elseif ($server['basic']['type'] == "sof2")
        {
            $pattern = "/(.*) (.*) (.*) \"(.*)\"/"; 
            $fields = [ 1 => "score", 2 => "ping", 3 => "deaths", 4 => "name" ];
        }
        // (?) (SCORE) (?) (TIME) (?) "(RANK?)" "(NAME)"
        elseif (strpos($server['basic']['type'], "mohpa") !== FALSE)
        {
            $pattern = "/(.*) (.*) (.*) (.*) (.*) \"(.*)\" \"(.*)\"/"; 
            $fields = [ 2 => "score", 3 => "deaths", 4 => "time", 6 => "rank", 7 => "name" ];
        }
        // (PING) "(NAME)"
        elseif (strpos($server['basic']['type'], "moh") !== FALSE)
        {
            $pattern = "/(.*) \"(.*)\"/"; 
            $fields = [ 1 => "ping", 2 => "name" ];
        }
        // (SCORE) (PING) "(NAME)"
        else
        {
            $pattern = "/(.*) (.*) \"(.*)\"/"; 
            $fields = [ 1 => "score", 2 => "ping", 3 => "name" ];
        }

        //---------------------------------------------------------+
        foreach ($part as $player_key => $data)
        {
            if (!$data) { continue; }

            preg_match($pattern, $data, $match);

            foreach ($fields as $match_key => $field_name)
            {
                if (isset($match[$match_key])) { $server['players'][$player_key][$field_name] = trim($match[$match_key]); }
            }

            $server['players'][$player_key]['name'] = Functions::ParserColor($server['players'][$player_key]['name'], "1");

            if (isset($server['players'][$player_key]['time']))
            {
                $server['players'][$player_key]['time'] = Functions::Time($server['players'][$player_key]['time']);
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
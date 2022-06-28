<?php
namespace GrinJackal\LGSQ\Protocols;

class Query03 extends Query02{
    public static function Query03(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        // BF1942 BUG: RETURNS 'GHOST' NAMES - TO SKIP THESE WE NEED AN [s] REQUEST FOR AN ACCURATE PLAYER COUNT
        if ($server['basic']['type'] == "bf1942" && $lgsl_need['p'] && !$lgsl_need['s'] && !isset($lgsl_need['sp'])) { $lgsl_need['s'] = TRUE; $lgsl_need['sp'] = TRUE; }
  
        if     ($server['basic']['type'] == "cncrenegade") { fwrite($lgsl_fp, "\\status\\"); }
        elseif ($lgsl_need['s'] || $lgsl_need['c'])    { fwrite($lgsl_fp, "\\basic\\\\info\\\\rules\\"); $lgsl_need['s'] = FALSE; $lgsl_need['c'] = FALSE; }
        elseif ($lgsl_need['p'])                       { fwrite($lgsl_fp, "\\players\\");                $lgsl_need['p'] = FALSE; }
  
        //---------------------------------------------------------+
        $buffer = "";
        $queryid = "";
        $packet_count = 0;
        $packet_total = 20;
  
        do
        {
            $packet = fread($lgsl_fp, 4096);
  
            // QUERY PORT CHECK AS THE CONNECTION PORT WILL ALSO RESPOND
            if (strpos($packet, "\\") === FALSE) { return FALSE; }
  
            // REMOVE SLASH PREFIX
            if ($packet[0] == "\\") { $packet = substr($packet, 1); }
  
            while ($packet)
            {
                $key   = strtolower(Functions::CutString($packet, 0, "\\"));
                $value =       trim(Functions::CutString($packet, 0, "\\"));
  
                // CHECK IF KEY IS PLAYER DATA
                if (preg_match("/(.*)_([0-9]+)$/", $key, $match))
                {
                    // SEPERATE TEAM NAMES
                    if ($match[1] == "teamname") { $server['teams'][$match[2]]['name'] = $value; continue; }
                
                    // CONVERT TO LGSL STANDARD
                    if     ($match[1] == "player")     { $match[1] = "name";  }
                    elseif ($match[1] == "playername") { $match[1] = "name";  }
                    elseif ($match[1] == "frags")      { $match[1] = "score"; }
                    elseif ($match[1] == "ngsecret")   { $match[1] = "stats"; }
                
                    $server['players'][$match[2]][$match[1]] = $value; 
                    continue;
                }
  
                // SEPERATE QUERYID
                if ($key == "queryid") { 
                    $queryid = $value; 
                    continue; 
                }
  
                // SERVER SETTING
                $server['convars'][$key] = $value;
            }
  
            // FINAL PACKET NUMBER IS THE TOTAL
            if (isset($server['convars']['final']))
            {
                preg_match("/([0-9]+)\.([0-9]+)/", $queryid, $match);
                $packet_total = intval($match[2]);
                unset($server['convars']['final']);
            }
  
            $packet_count++;
        }
        while ($packet_count < $packet_total);
  
        //---------------------------------------------------------+
        if (isset($server['convars']['mapname']))
        {
            $server['server']['map'] = $server['convars']['mapname'];
  
            if (!empty($server['convars']['hostname']))    { $server['server']['name'] = $server['convars']['hostname']; }
            if (!empty($server['convars']['sv_hostname'])) { $server['server']['name'] = $server['convars']['sv_hostname']; }
  
            if (isset($server['convars']['password']))   { $server['server']['password']   = $server['convars']['password']; }
            if (isset($server['convars']['numplayers'])) { $server['server']['players']    = $server['convars']['numplayers']; }
            if (isset($server['convars']['maxplayers'])) { $server['server']['playersmax'] = $server['convars']['maxplayers']; }
  
            if (!empty($server['convars']['gamename']))                                         { $server['server']['game'] = $server['convars']['gamename']; }
            if (!empty($server['convars']['gameid']) && empty($server['convars']['gamename']))  { $server['server']['game'] = $server['convars']['gameid']; }
            if (!empty($server['convars']['gameid']) && $server['basic']['type'] == "bf1942")   { $server['server']['game'] = $server['convars']['gameid']; }
        }
  
        //---------------------------------------------------------+
        if ($server['players'])
        {
            // BF1942 BUG - REMOVE 'GHOST' PLAYERS
            if ($server['basic']['type'] == "bf1942" && $server['server']['players'])
            {
                $server['players'] = array_slice($server['players'], 0, $server['server']['players']);
            }
  
            // OPERATION FLASHPOINT BUG: 'GHOST' PLAYERS IN UN-USED 'TEAM' FIELD
            if ($server['basic']['type'] == "flashpoint")
            {
                foreach ($server['players'] as $key => $value)
                {
                    unset($server['players'][$key]['team']);
                }
            }
  
            // AVP2 BUG: PLAYER NUMBER PREFIXED TO NAMES
            if ($server['basic']['type'] == "avp2")
            {
                foreach ($server['players'] as $key => $value)
                {
                    $server['players'][$key]['name'] = preg_replace("/[0-9]+~/", "", $server['players'][$key]['name']);
                }
            }
  
            // IF TEAM NAMES AVAILABLE USED INSTEAD OF TEAM NUMBERS
            if (isset($server['teams'][0]['name']))
            {
                foreach ($server['players'] as $key => $value)
                {
                    $team_key = $server['players'][$key]['team'] - 1;
                    $server['players'][$key]['team'] = $server['teams'][$team_key]['name'];
                }
            }
  
            // RE-INDEX PLAYER KEYS TO REMOVE ANY GAPS
            $server['players'] = array_values($server['players']);
        }
        return TRUE;
    }
}
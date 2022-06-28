<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query21 extends Query20{
    public static function Query21(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp,"\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffgief");
  
        $buffer = fread($lgsl_fp, 4096);
        $buffer = substr($buffer, 20); // REMOVE HEADER
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $server['server']['name']           = Functions::CutString($buffer);
        $server['server']['map']            = Functions::CutString($buffer);
        $server['convars']['gamemode']      = Functions::CutString($buffer);
        $server['server']['password']       = Functions::CutString($buffer);
        $server['convars']['progress']      = Functions::CutString($buffer)."%";
        $server['server']['players']        = Functions::CutString($buffer);
        $server['server']['playersmax']     = Functions::CutString($buffer);
  
        switch ($server['convars']['gamemode'])
        {
            case 0: $server['convars']['gamemode'] = "Deathmatch"; break;
            case 1: $server['convars']['gamemode'] = "Team Deathmatch"; break;
            case 2: $server['convars']['gamemode'] = "Capture The Flag"; break;
        }
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer)
        {
            $server['players'][$player_key]['name']  = Functions::CutString($buffer);
            $server['players'][$player_key]['score'] = Functions::CutString($buffer);
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
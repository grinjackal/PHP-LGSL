<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query25 extends Query24{
    public static function Query25(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://www.tribesnext.com
        fwrite($lgsl_fp,"\x12\x02\x21\x21\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 6); // REMOVE HEADER

        //---------------------------------------------------------+
        $server['server']['game']           = Functions::CutPascal($buffer);
        $server['convars']['gamemode']      = Functions::CutPascal($buffer);
        $server['server']['map']            = Functions::CutPascal($buffer);
        $server['convars']['bit_flags']     = ord(Functions::CutByte($buffer, 1));
        $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
        $server['convars']['bots']          = ord(Functions::CutByte($buffer, 1));
        $server['convars']['cpu']           = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['convars']['motd']          = Functions::CutPascal($buffer);
        $server['convars']['unknown']       = Functions::UnPack(Functions::CutByte($buffer, 2), "S");

        $server['convars']['dedicated']     = ($server['convars']['bit_flags'] & 1)  ? "1" : "0";
        $server['server']['password']       = ($server['convars']['bit_flags'] & 2)  ? "1" : "0";
        $server['convars']['os']            = ($server['convars']['bit_flags'] & 4)  ? "L" : "W";
        $server['convars']['tournament']    = ($server['convars']['bit_flags'] & 8)  ? "1" : "0";
        $server['convars']['no_alias']      = ($server['convars']['bit_flags'] & 16) ? "1" : "0";

        unset($server['convars']['bit_flags']);

        //---------------------------------------------------------+
        $team_total = Functions::CutString($buffer, 0, "\x0A");

        for ($i=0; $i<$team_total; $i++)
        {
            $server['teams'][$i]['name']  = Functions::CutString($buffer, 0, "\x09");
            $server['teams'][$i]['score'] = Functions::CutString($buffer, 0, "\x0A");
        }

        $player_total = Functions::CutString($buffer, 0, "\x0A");

        for ($i=0; $i<$player_total; $i++)
        {
            Functions::CutByte($buffer, 1); // ? 16
            Functions::CutByte($buffer, 1); // ? 8 or 14 = BOT / 12 = ALIAS / 11 = NORMAL
            if (ord($buffer[0]) < 32) { 
                Functions::CutByte($buffer, 1); 
            } // ? 8 PREFIXES SOME NAMES

            $server['players'][$i]['name']  = Functions::CutString($buffer, 0, "\x11");
            Functions::CutString($buffer, 0, "\x09"); // ALWAYS BLANK
            $server['players'][$i]['team']  = Functions::CutString($buffer, 0, "\x09");
            $server['players'][$i]['score'] = Functions::CutString($buffer, 0, "\x0A");
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
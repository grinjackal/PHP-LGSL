<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query29 extends Query28{
    public static function Query29(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://www.cs2d.com/servers.php
        if ($lgsl_need['s'] || $lgsl_need['c']){
            $lgsl_need['s'] = FALSE;
            $lgsl_need['c'] = FALSE;

            fwrite($lgsl_fp, "\x01\x00\x03\x10\x21\xFB\x01\x75\x00");

            $buffer = fread($lgsl_fp, 4096);

            if (!$buffer) { return FALSE; }

            $buffer = substr($buffer, 4); // REMOVE HEADER

            $server['convars']['bit_flags']     = ord(Functions::CutByte($buffer, 1)) - 48;
            $server['server']['name']           = Functions::CutPascal($buffer);
            $server['server']['map']            = Functions::CutPascal($buffer);
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
            $server['convars']['gamemode']      = ord(Functions::CutByte($buffer, 1));
            $server['convars']['bots']          = ord(Functions::CutByte($buffer, 1));

            $server['server']['password']           = ($server['convars']['bit_flags'] & 1) ? "1" : "0";
            $server['convars']['registered_only']   = ($server['convars']['bit_flags'] & 2) ? "1" : "0";
            $server['convars']['fog_of_war']        = ($server['convars']['bit_flags'] & 4) ? "1" : "0";
            $server['convars']['friendlyfire']      = ($server['convars']['bit_flags'] & 8) ? "1" : "0";
        }

        if ($lgsl_need['p'])
        {
            $lgsl_need['p'] = FALSE;

            fwrite($lgsl_fp, "\x01\x00\xFB\x05");

            $buffer = fread($lgsl_fp, 4096);

            if (!$buffer) { return FALSE; }

            $buffer = substr($buffer, 4); // REMOVE HEADER

            $player_total = ord(Functions::CutByte($buffer, 1));

            for ($i = 0; $i < $player_total; $i++)
            {
                $server['players'][$i]['pid']    = ord(Functions::CutByte($buffer, 1));
                $server['players'][$i]['name']   = Functions::CutPascal($buffer);
                $server['players'][$i]['team']   = ord(Functions::CutByte($buffer, 1));
                $server['players'][$i]['score']  = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
                $server['players'][$i]['deaths'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
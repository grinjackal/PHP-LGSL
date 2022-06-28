<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query31 extends Query30{
    public static function Query31(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  AVP 2010 ONLY ROUGHLY FOLLOWS THE SOURCE QUERY FORMAT
        //  SERVER RULES ARE ON THE END OF THE INFO RESPONSE
        fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 5); // REMOVE HEADER

        $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
        $server['server']['name']           = Functions::CutString($buffer);
        $server['server']['map']            = Functions::CutString($buffer);
        $server['server']['game']           = Functions::CutString($buffer);
        $server['convars']['description']   = Functions::CutString($buffer);
        $server['convars']['appid']         = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
        $server['convars']['bots']          = ord(Functions::CutByte($buffer, 1));
        $server['convars']['dedicated']     = Functions::CutByte($buffer, 1);
        $server['convars']['os']            = Functions::CutByte($buffer, 1);
        $server['server']['password']       = ord(Functions::CutByte($buffer, 1));
        $server['convars']['anticheat']     = ord(Functions::CutByte($buffer, 1));
        $server['convars']['version']       = Functions::CutString($buffer);

        $buffer = substr($buffer, 1);
        $server['convars']['hostport']     = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['convars']['friendlyfire'] = $buffer[124];

        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
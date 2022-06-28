<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query41 extends Query40{
    public static function Query41(&$server, &$lgsl_need, &$lgsl_fp) // ONLY BEACON: World of Warcraft, Satisfactory
    {
        if (!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        if ($server['basic']['type'] == 'wow') {
            $buffer = fread($lgsl_fp, 5);
            if ($buffer && $buffer == "\x00\x2A\xEC\x01\x01") {
                $server['server']['name']        = "World of Warcraft Server";
                $server['server']['map']         = "Twisting Nether";
                return TRUE;
            }
            return FALSE;
        }

        if ($server['basic']['type'] == 'sf') {
            fwrite($lgsl_fp, "\x00\x00\xd6\x9c\x28\x25\x00\x00\x00\x00");
            $buffer = fread($lgsl_fp, 128);
            if (!$buffer) {
                return FALSE;
            }
            Functions::CutByte($buffer, 11);
            $version                            = Functions::UnPack(Functions::CutByte($buffer, 1), "H*");
            $version                            = Functions::UnPack(Functions::CutByte($buffer, 1), "H*") . $version;
            $version                            = Functions::UnPack(Functions::CutByte($buffer, 1), "H*") . $version;
            $server['server']['name']           = "Satisfactory Dedicated Server";
            $server['server']['map']            = "World";
            $server['convars']['version']       = hexdec($version);
            return TRUE;
        }
        return FALSE;
    }
}
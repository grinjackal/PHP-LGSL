<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query28 extends Query27{
    public static function Query28(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://doomutils.ucoz.com
        fwrite($lgsl_fp, "\xA3\xDB\x0B\x00"."\xFC\xFD\xFE\xFF"."\x01\x00\x00\x00"."\x21\x21\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $response_status  = Functions::UnPack(Functions::CutByte($buffer, 4), "l"); if ($response_status != "5560022") { return FALSE; }
        $response_version = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        $response_time    = Functions::UnPack(Functions::CutByte($buffer, 4), "l");

        $server['convars']['invited']       = ord(Functions::CutByte($buffer, 1));
        $server['convars']['version']       = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['server']['name']           = Functions::CutString($buffer);
        $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
        $server['server']['map']            = Functions::CutString($buffer);

        $pwad_total = ord(Functions::CutByte($buffer, 1));

        for ($i=0; $i<$pwad_total; $i++)
        {
            $server['convars']['pwads'] .= Functions::CutString($buffer)." ";
            $pwad_optional              = ord(Functions::CutByte($buffer, 1));
            $pwad_alternative           = Functions::CutString($buffer);
        }

        $server['convars']['gametype']      = ord(Functions::CutByte($buffer, 1));
        $server['server']['game']           = Functions::CutString($buffer);
        $server['convars']['iwad']          = Functions::CutString($buffer);
        $iwad_altenative                    = Functions::CutString($buffer);
        $server['convars']['skill']         = ord(Functions::CutByte($buffer, 1)) + 1;
        $server['convars']['wadurl']        = Functions::CutString($buffer);
        $server['convars']['email']         = Functions::CutString($buffer);
        $server['convars']['dmflags']       = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        $server['convars']['dmflags2']      = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        $server['server']['password']       = ord(Functions::CutByte($buffer, 1));
        $server['convars']['inviteonly']    = ord(Functions::CutByte($buffer, 1));
        $server['convars']['players']       = ord(Functions::CutByte($buffer, 1));
        $server['convars']['playersmax']    = ord(Functions::CutByte($buffer, 1));
        $server['convars']['timelimit']     = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 2), "S") * 60);
        $server['convars']['timeleft']      = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 2), "S") * 60);
        $server['convars']['fraglimit']     = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
        $server['convars']['gravity']       = Functions::UnPack(Functions::CutByte($buffer, 4), "f");
        $server['convars']['aircontrol']    = Functions::UnPack(Functions::CutByte($buffer, 4), "f");
        $server['convars']['playersmin']    = ord(Functions::CutByte($buffer, 1));
        $server['convars']['removebots']    = ord(Functions::CutByte($buffer, 1));
        $server['convars']['voting']        = ord(Functions::CutByte($buffer, 1));
        $server['convars']['serverinfo']    = Functions::CutString($buffer);
        $server['convars']['startup']       = Functions::UnPack(Functions::CutByte($buffer, 4), "l");

        for ($i = 0; $i < $server['server']['players']; $i++)
        {
            $server['players'][$i]['name']      = Functions::CutString($buffer);
            $server['players'][$i]['score']     = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $server['players'][$i]['death']     = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $server['players'][$i]['ping']      = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['players'][$i]['time']      = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 2), "S") * 60);
            $server['players'][$i]['bot']       = ord(Functions::CutByte($buffer, 1));
            $server['players'][$i]['spectator'] = ord(Functions::CutByte($buffer, 1));
            $server['players'][$i]['team']      = ord(Functions::CutByte($buffer, 1));
            $server['players'][$i]['country']   = Functions::CutByte($buffer, 2);
        }

        $team_total                         = ord(Functions::CutByte($buffer, 1));
        $server['convars']['pointlimit']    = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
        $server['convars']['teamdamage']    = Functions::UnPack(Functions::CutByte($buffer, 4), "f");

        for ($i = 0; $i < $team_total; $i++) // RETURNS 4 TEAMS BUT IGNORE THOSE NOT IN USE
        {
            $server['teams']['team'][$i]['name']  = Functions::CutString($buffer);
            $server['teams']['team'][$i]['color'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
            $server['teams']['team'][$i]['score'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
        }

        for ($i = 0; $i < $server['server']['players']; $i++)
        {
            if ($server['teams'][$i]['name']) { 
                $server['players'][$i]['team'] = $server['teams'][$i]['name']; 
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
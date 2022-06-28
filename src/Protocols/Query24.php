<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query24 extends Query23{
    public static function Query24(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://cubelister.sourceforge.net
        fwrite($lgsl_fp, "\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 2); // REMOVE HEADER

        //---------------------------------------------------------+
        if ($buffer[0] == "\x1b") // CUBE 1
        {
            // RESPONSE IS XOR ENCODED FOR SOME STRANGE REASON
            for ($i = 0; $i < strlen($buffer); $i++) { 
                $buffer[$i] = chr(ord($buffer[$i]) ^ 0x61); 
            }

            $server['server']['game']           = "Cube";
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['gamemode']      = ord(Functions::CutByte($buffer, 1));
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $server['convars']['timeleft']      = Functions::Time(ord(Functions::CutByte($buffer, 1)) * 60);
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['name']           = Functions::CutString($buffer);
            $server['server']['playersmax']     = "0"; // NOT PROVIDED

            // DOES NOT RETURN PLAYER INFORMATION
            return TRUE;
        }

        elseif ($buffer[0] == "\x80") // ASSAULT CUBE
        {
            $server['server']['game']           = "AssaultCube";
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['version']       = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']      = ord(Functions::CutByte($buffer, 1));
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $server['convars']['timeleft']      = Functions::Time(ord(Functions::CutByte($buffer, 1)) * 60);
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['name']           = Functions::CutString($buffer);
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
        }

        elseif ($buffer[1] == "\x05") // CUBE 2 - SAUERBRATEN
        {
            $server['server']['game']           = "Sauerbraten";
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $info_returned                      = ord(Functions::CutByte($buffer, 1)); // CODED FOR 5
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['version']       = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']      = ord(Functions::CutByte($buffer, 1));
            $server['convars']['timeleft']      = Functions::Time(ord(Functions::CutByte($buffer, 1)) * 60);
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
            $server['server']['password']       = ord(Functions::CutByte($buffer, 1)); // BIT FIELD
            $server['server']['password']       = $server['server']['password'] & 4 ? "1" : "0";
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['name']           = Functions::CutString($buffer);
        }

        elseif ($buffer[1] == "\x06") // BLOODFRONTIER
        {
            $server['server']['game']           = "Blood Frontier";
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $info_returned                      = ord(Functions::CutByte($buffer, 1)); // CODED FOR 6
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['version']       = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']      = ord(Functions::CutByte($buffer, 1));
            $server['convars']['mutators']      = ord(Functions::CutByte($buffer, 1));
            $server['convars']['timeleft']      = Functions::Time(ord(Functions::CutByte($buffer, 1)) * 60);
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
            $server['server']['password']       = ord(Functions::CutByte($buffer, 1)); // BIT FIELD
            $server['server']['password']       = $server['server']['password'] & 4 ? "1" : "0";
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['name']           = Functions::CutString($buffer);
        }

        else // UNKNOWN
        {
            return FALSE;
        }

        //---------------------------------------------------------+
        //  CRAZY PROTOCOL - REQUESTS MUST BE MADE FOR EACH PLAYER
        //  BOTS ARE RETURNED BUT NOT INCLUDED IN THE PLAYER TOTAL
        //  AND THERE CAN BE ID GAPS BETWEEN THE PLAYERS RETURNED

        if ($lgsl_need['p'] && $server['server']['players'])
        {
            $player_key = 0;

            for ($player_id=0; $player_id<32; $player_id++)
            {
                fwrite($lgsl_fp, "\x00\x01".chr($player_id));

                // READ PACKET
                $buffer = fread($lgsl_fp, 4096);
                if (!$buffer) { break; }

                // CHECK IF PLAYER ID IS ACTIVE
                if ($buffer[5] != "\x00")
                {
                    if ($player_key < $server['server']['players']) { continue; }
                    break;
                }

                // IF PREVIEW PACKET GET THE FULL PACKET THAT FOLLOWS
                if (strlen($buffer) < 15)
                {
                    $buffer = fread($lgsl_fp, 4096);
                    if (!$buffer) { break; }
                }

                // REMOVE HEADER
                $buffer = substr($buffer, 7);

                // WE CAN NOW GET THE PLAYER DETAILS
                if ($server['server']['game'] == "Blood Frontier")
                {
                    $server['players'][$player_key]['pid']       = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['ping']      = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['ping']      = $server['players'][$player_key]['ping'] == 128 ? Functions::UnPack(Functions::CutByte($buffer, 2), "S") : $server['players'][$player_key]['ping'];
                    $server['players'][$player_key]['name']      = Functions::CutString($buffer);
                    $server['players'][$player_key]['team']      = Functions::CutString($buffer);
                    $server['players'][$player_key]['score']     = Functions::UnPack(Functions::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['damage']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['deaths']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['teamkills'] = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['accuracy']  = Functions::UnPack(Functions::CutByte($buffer, 1), "C")."%";
                    $server['players'][$player_key]['health']    = Functions::UnPack(Functions::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['spree']     = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['weapon']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                }else{
                    $server['players'][$player_key]['pid']       = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['name']      = Functions::CutString($buffer);
                    $server['players'][$player_key]['team']      = Functions::CutString($buffer);
                    $server['players'][$player_key]['score']     = Functions::UnPack(Functions::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['deaths']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['teamkills'] = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['accuracy']  = Functions::UnPack(Functions::CutByte($buffer, 1), "C")."%";
                    $server['players'][$player_key]['health']    = Functions::UnPack(Functions::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['armour']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['weapon']    = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
                }
                $player_key++;
            }
        }

        //----------------------------------------------------------
        return TRUE;
    }
}
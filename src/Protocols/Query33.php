<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query33 extends Query32{
    public static function Query33(&$server, &$lgsl_need, &$lgsl_fp)
    {
        if (strpos(fread($lgsl_fp, 4096), 'TS') === FALSE) {
            if (strpos(fread($lgsl_fp, 4096), 'TeaSpeak') === FALSE) {
                return FALSE;
            }
        }

        $ver = $server['basic']['type'] == 'ts' ? 0 : 1;
        $param[0] = [ 'sel ', 'si',"\r\n", 'pl' ];
        $param[1] = [ 'use port=', 'serverinfo', ' ','clientlist -country', 'channellist -topic' ];

        if ($ver) { 
            fread($lgsl_fp, 4096); 
        }

        fwrite($lgsl_fp, $param[$ver][0].$server['basic']['c_port']."\n"); // select virtualserver
        if (strtoupper(substr(fread($lgsl_fp, 4096), -4, -2)) != 'OK') { 
            return FALSE; 
        }

        fwrite($lgsl_fp, $param[$ver][1]."\n"); // request serverinfo
        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer || substr($buffer, 0, 5) == 'error') { 
            return FALSE; 
        }

        while (strtoupper(substr($buffer, -4, -2)) != 'OK') {
            $part = fread($lgsl_fp, 4096);
            if ($part && substr($part, 0, 5) != 'error') { $buffer .= $part; } else { break; }
        }

        while ($val = Functions::CutString($buffer, 7+7*$ver, $param[$ver][2])) {
            $key = Functions::CutString($val, 0, '='); $items[$key] = $val;
        }

        if (!isset($items['name'])) { 
            return FALSE; 
        }

        $server['server']['name']           = $ver ? Functions::UnEscape($items['name']) : $items['name'];
        $server['server']['map']            = $server['basic']['type'];
        $server['server']['players']        = intval($items[$ver ? 'clientsonline' : 'currentusers']) - $ver;
        $server['server']['playersmax']     = intval($items[$ver ? 'maxclients' : 'maxusers']);
        $server['server']['password']       = intval($items[$ver ? 'flag_password' : 'password']);
        $server['convars']['platform']      = $items['platform'];
        $server['convars']['motd']          = $ver ? Functions::UnEscape($items['welcomemessage']) : $items['welcomemessage'];
        $server['convars']['uptime']        = Functions::Time($items['uptime']);
        $server['convars']['channels']      = $items[$ver ? 'channelsonline' : 'currentchannels'];
    
        if ($ver) { $server['convars']['version'] = Functions::UnEscape($items['version']); }
        if (!$lgsl_need['p'] || $server['server']['players'] < 1) { return TRUE; }

        fwrite($lgsl_fp, $param[$ver][3]."\n"); // request playerlist
        $buffer = fread($lgsl_fp, 4096);

        while (substr($buffer, -4) != "OK\r\n" && substr($buffer, -2) != "\n\r") {
            $part = fread($lgsl_fp, 4096);
            if ($part && substr($part, 0, 5) != 'error') { 
                $buffer .= $part; 
            } else { 
                break; 
            }
        }

        $i = 0;
        if ($ver) {
            while ($items = Functions::CutString($buffer, 0, '|')) {
                Functions::CutString($items, 0, 'e='); 
                $name = Functions::CutString($items, 0, ' ');

                if (substr($name, 0, 15) == 'Unknown\sfrom\s') { continue; }

                $server['players'][$i]['name']      = Functions::UnEscape($name); Functions::CutString($items, 0, 'ry');
                $server['players'][$i]['country']   = substr($items, 0, 1) == '=' ? substr($items, 1, 2) : ''; $i++;
            }
        }else {
            $buffer = substr($buffer, 89, -4);
            while ($items = Functions::CutString($buffer, 0, "\r\n")) {
                $items = explode("\t", $items);
                $server['players'][$i]['name'] = substr($items[14], 1, -1);
                $server['players'][$i]['ping'] = $items[7];
                $server['players'][$i]['time'] = Functions::Time($items[8]); $i++;
            }
        }

        if($ver){
            fwrite($lgsl_fp, $param[$ver][4]."\n"); // request channellist
            $buffer = fread($lgsl_fp, 4096);
            while (substr($buffer, -4) != "OK\r\n" && substr($buffer, -2) != "\n\r") {

                $part = fread($lgsl_fp, 4096);
                if ($part && substr($part, 0, 5) != 'error') {
                    $buffer .= $part; 
                } else { 
                    break; 
                }
            }
            while ($items = Functions::CutString($buffer, 0, '|')) {
                $id = Functions::CutString($items, 4, ' ');
                Functions::CutString($items, 0, 'e=');
                $name = Functions::CutString($items, 0, ' ');
                if(strpos($name, '*spacer') != FALSE) { continue; }
                $server['convars']['channel'.$id] = Functions::UnEscape($name);
            }
        }
        return TRUE;
    }
}
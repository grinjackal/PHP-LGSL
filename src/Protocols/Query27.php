<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query27 extends Query26{
    public static function Query27(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://skulltag.com/wiki/Launcher_protocol
        //  http://en.wikipedia.org/wiki/Huffman_coding
        //  http://www.greycube.com/help/lgsl_other/skulltag_huffman.txt

        $huffman_table = [
            "010","110111","101110010","00100","10011011","00101","100110101","100001100","100101100","001110100","011001001","11001000","101100001","100100111","001111111","101110000","101110001","001111011",
            "11011011","101111100","100001110","110011111","101100000","001111100","0011000","001111000","10001100","100101011","100010000","101111011","100100110","100110010","0111","1111000","00010001",
            "00011010","00011000","00010101","00010000","00110111","00110110","00011100","01100101","1101001","00110100","10110011","10110100","1111011","10111100","10111010","11001001","11010101","11111110",
            "11111100","10001110","11110011","001101011","10000000","000101101","11010000","001110111","100000010","11100111","001100101","11100110","00111001","10001010","00010011","001110110","10001111",
            "000111110","11000111","11010111","11100011","000101000","001100111","11010100","000111010","10010111","100000111","000100100","001110001","11111010","100100011","11110100","000110111","001111010",
            "100010011","100110001","11101","110001011","101110110","101111110","100100010","100101001","01101","100100100","101100101","110100011","100111100","110110001","100010010","101101101","011001110",
            "011001101","11111101","100010001","100110000","110001000","110110000","0001001010","110001010","101101010","000110110","10110001","110001101","110101101","110001100","000111111","110010101",
            "111000100","11011001","110010110","110011110","000101100","001110101","101111101","1001110","0000","1000010","0001110111","0001100101","1010","11001110","0110011000","0110011001","1000011011",
            "1001100110","0011110011","0011001100","11111001","0110010001","0001010011","1000011010","0001001011","1001101001","101110111","1000001101","1000011111","1100000101","0110000000","1011011101",
            "11110101","0001111011","1101000101","1101000100","1001000010","0110000001","1011001000","100101010","1100110","111100101","1100101111","0001100111","1110000","0011111100","11111011","1100101110",
            "101110011","1001100111","1001111111","1011011100","111110001","101111010","1011010110","1001010000","1001000011","1001111110","0011111011","1000011110","1000101100","01100001","00010111",
            "1000000110","110000101","0001111010","0011001101","0110011110","110010100","111000101","0011001001","0011110010","110000001","101101111","0011111101","110110100","11100100","1011001001",
            "0011001000","0001110110","111111111","110101100","111111110","1000001011","1001011010","110000000","000111100","111110000","011000001","1001111010","111001011","011000111","1001000001",
            "1001111100","1000110111","1001101000","0110001100","1001111011","0011010101","1000101101","0011111010","0001100100","01100010","110000100","101101100","0110011111","1001011011","1000101110",
            "111100100","1000110110","0110001101","1001000000","110110101","1000001000","1000001001","1100000100","110001001","1000000111","1001111101","111001010","0011010100","1000101111","101111111",
            "0001010010","0011100000","0001100110","1000001010","0011100001","11000011","1011010111","1000001100","100011010","0110010000","100100101","1001010001","110000011"
        ];

        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\x02\xB8\x49\x1A\x9C\x8B\xB5\x3F\x1E\x8F\x07");

        $packet = fread($lgsl_fp, 4096);

        if (!$packet) { return FALSE; }

        $packet = substr($packet, 1); // REMOVE HEADER

        //---------------------------------------------------------+
        $packet_binary = "";

        for ($i = 0; $i < strlen($packet); $i++)
        {
            $packet_binary .= strrev(sprintf("%08b", ord($packet[$i])));
        }

        $buffer = "";

        while ($packet_binary)
        {
            foreach ($huffman_table as $ascii => $huffman_binary)
            {
                $huffman_length = strlen($huffman_binary);
                if (substr($packet_binary, 0, $huffman_length) === $huffman_binary)
                {
                    $packet_binary = substr($packet_binary, $huffman_length);
                    $buffer .= chr($ascii);
                    continue 2;
                }
            }
            break;
        }

        //---------------------------------------------------------+
        $response_status        = Functions::UnPack(Functions::CutByte($buffer, 4), "l"); if ($response_status != "5660023") { return FALSE; }
        $response_time          = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        $server['convars']['version'] = Functions::CutString($buffer);
        $response_flag          = Functions::UnPack(Functions::CutByte($buffer, 4), "l");

        //---------------------------------------------------------+
        if ($response_flag & 0x00000001) { $server['server']['name']        = Functions::CutString($buffer); }
        if ($response_flag & 0x00000002) { $server['convars']['wadurl']     = Functions::CutString($buffer); }
        if ($response_flag & 0x00000004) { $server['convars']['email']      = Functions::CutString($buffer); }
        if ($response_flag & 0x00000008) { $server['server']['map']         = Functions::CutString($buffer); }
        if ($response_flag & 0x00000010) { $server['server']['playersmax']  = ord(Functions::CutByte($buffer, 1)); }
        if ($response_flag & 0x00000020) { $server['convars']['playersmax'] = ord(Functions::CutByte($buffer, 1)); }
        
        if ($response_flag & 0x00000040){
            $pwad_total = ord(Functions::CutByte($buffer, 1));
            $server['convars']['pwads'] = "";
            for ($i = 0; $i < $pwad_total; $i++)
            {
                $server['convars']['pwads'] .= Functions::CutString($buffer)." ";
            }
        }

        if ($response_flag & 0x00000080){
            $server['convars']['gametype'] = ord(Functions::CutByte($buffer, 1));
            $server['convars']['instagib'] = ord(Functions::CutByte($buffer, 1));
            $server['convars']['buckshot'] = ord(Functions::CutByte($buffer, 1));
        }
        
        if ($response_flag & 0x00000100) { $server['server']['game']            = Functions::CutString($buffer); }
        if ($response_flag & 0x00000200) { $server['convars']['iwad']           = Functions::CutString($buffer); }
        if ($response_flag & 0x00000400) { $server['server']['password']        = ord(Functions::CutByte($buffer, 1)); }
        if ($response_flag & 0x00000800) { $server['convars']['playpassword']   = ord(Functions::CutByte($buffer, 1)); }
        if ($response_flag & 0x00001000) { $server['convars']['skill']          = ord(Functions::CutByte($buffer, 1)) + 1; }
        if ($response_flag & 0x00002000) { $server['convars']['botskill']       = ord(Functions::CutByte($buffer, 1)) + 1; }
        
        if ($response_flag & 0x00004000){
            $server['convars']['dmflags']     = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
            $server['convars']['dmflags2']    = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
            $server['convars']['compatflags'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        }
        
        if ($response_flag & 0x00010000){
            $server['convars']['fraglimit'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $timelimit                      = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            if ($timelimit){
                $server['convars']['timeleft'] = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 2), "S") * 60);
            }
            $server['convars']['timelimit']  = Functions::Time($timelimit * 60);
            $server['convars']['duellimit']  = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $server['convars']['pointlimit'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $server['convars']['winlimit']   = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
        }

        if ($response_flag & 0x00020000) { $server['convars']['teamdamage'] = Functions::UnPack(Functions::CutByte($buffer, 4), "f"); }
        
        if ($response_flag & 0x00040000){
            $server['teams'][0]['score'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
            $server['teams'][1]['score'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
        }
        if ($response_flag & 0x00080000) { $server['server']['players'] = ord(Functions::CutByte($buffer, 1)); }
        
        if ($response_flag & 0x00100000){
            for ($i = 0; $i < $server['server']['players']; $i++){
                $server['players'][$i]['name']      = Functions::ParserColor(Functions::CutString($buffer), $server['basic']['type']);
                $server['players'][$i]['score']     = Functions::UnPack(Functions::CutByte($buffer, 2), "s");
                $server['players'][$i]['ping']      = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $server['players'][$i]['spectator'] = ord(Functions::CutByte($buffer, 1));
                $server['players'][$i]['bot']       = ord(Functions::CutByte($buffer, 1));

                if (($response_flag & 0x00200000) && ($response_flag & 0x00400000)){
                    $server['players'][$i]['team'] = ord(Functions::CutByte($buffer, 1));
                }
                $server['players'][$i]['time'] = Functions::Time(ord(Functions::CutByte($buffer, 1)) * 60);
            }
        }

        if ($response_flag & 0x00200000){
            $team_total = ord(Functions::CutByte($buffer, 1));

            if ($response_flag & 0x00400000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['name'] = Functions::CutString($buffer); 
                }
            }

            if ($response_flag & 0x00800000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['color'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l"); 
                }
            }

            if ($response_flag & 0x01000000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['score'] = Functions::UnPack(Functions::CutByte($buffer, 2), "s"); 
                }
            }

            for ($i=0; $i<$server['server']['players']; $i++){
                if ($server['teams'][$i]['name']) { 
                    $server['players'][$i]['team'] = $server['teams'][$i]['name']; 
                }
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }
}
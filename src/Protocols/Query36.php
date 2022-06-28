<?php
namespace GrinJackal\LGSQ\Protocols;

class Query36 extends Query35{
    public static function Query36(&$server, &$lgsl_need, &$lgsl_fp) // Discord
    {
        if(!$lgsl_fp){
            return FALSE;
        }

        $lgsl_need['s'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "https://discord.com/api/v9/invites/{$server['basic']['ip']}?with_counts=true");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(isset($buffer['message'])){
            $server['convars']['_error_fetching_info'] = $buffer['message'];
            return FALSE;
        }

        $server['server']['map']        = 'discord';
        $server['server']['name']       = $buffer['guild']['name'];
        $server['server']['players']    = $buffer['approximate_presence_count'];
        $server['server']['playersmax'] = $buffer['approximate_member_count'];
        $server['convars']['id']        = $buffer['guild']['id'];

        if($buffer['guild']['description']){
            $server['convars']['description'] = $buffer['guild']['description'];
        }

        if(isset($buffer['guild']['welcome_screen']) && isset($buffer['guild']['welcome_screen']['description'])){
            $server['convars']['description'] = $buffer['guild']['welcome_screen']['description'];
        }

        $server['convars']['features'] = implode(', ', $buffer['guild']['features']);
        $server['convars']['nsfw'] = (int) $buffer['guild']['nsfw'];
    
        if(isset($buffer['inviter'])){
            $server['convars']['inviter'] = $buffer['inviter']['username'] . "#" . $buffer['inviter']['discriminator'];
        }

        if($lgsl_need['p']) {
            $lgsl_need['p'] = FALSE;

            curl_setopt($lgsl_fp, CURLOPT_URL, "https://discordapp.com/api/guilds/{$server['convars']['id']}/widget.json");
            $buffer = curl_exec($lgsl_fp);
            $buffer = json_decode($buffer, true);

            if(isset($buffer['code']) and $buffer['code'] == 0){
                $server['convars']['_error_fetching_users'] = $buffer['message'];
            }

            if(isset($buffer['channels'])){
                foreach($buffer['channels'] as $key => $value){
                    $server['convars']['channel'.$key] = $value['name'];
                }
            }

            if(isset($buffer['members'])){
                foreach($buffer['members'] as $key => $value){
                    $server['players'][$key]['name']    = $value['username'];
                    $server['players'][$key]['status']  = $value['status'];
                    $server['players'][$key]['game']    = isset($value['game']) ? $value['game']['name'] : '--';
                }
            }
        }
        return TRUE;
    }
}
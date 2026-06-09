<?php

namespace BlackTea\SteamAuth\Listener;

use XF\Service\User\DeleteCleanUp;

class UserDeleteClean
{

    public static function run(DeleteCleanUp $deleteCleanUp, array &$deletes)
    {
        $deletes['xf_steamauth_game_user'] = 'user_id = ?';
    }

}
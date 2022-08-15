<?php

use Sunlight\User;

return function ($min_uroven = 0, $max_uroven = User::MAX_LEVEL, $vyhovujici_text = '', $nevyhovujici_text = '') {
    if (User::getLevel() >= (int) $min_uroven && User::getLevel() <= (int) $max_uroven) {
        return $vyhovujici_text;
    }

    return $nevyhovujici_text;
};

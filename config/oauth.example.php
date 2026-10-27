<?php
declare(strict_types=1);

/**
 * EduMate - OAuth Configuration Template
 *
 * Copy this file to config/oauth.php or config/oauth.local.php and insert your live API credentials.
 * Both config/oauth.php and config/oauth.local.php are ignored by git to protect your secrets.
 */

return [
    'google' => [
        'client_id'     => '', // e.g. 'xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com'
        'client_secret' => '', // e.g. 'GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx'
        'redirect_uri'  => 'http://localhost/WEBPAGE/eduMate/oauth2callback.php',
    ],
    'facebook' => [
        'app_id'        => '', // e.g. '123456789012345'
        'app_secret'    => '', // e.g. 'xxxxxxxxxxxxxxxxxxxxxxxx'
        'redirect_uri'  => 'http://localhost/WEBPAGE/eduMate/oauth.php?provider=facebook',
    ],
];

<?php
declare(strict_types=1);

/**
 * EduMate - OAuth Configuration
 *
 * To connect to live Google and Facebook authentication, fill in your API credentials.
 * If credentials are not set, EduMate seamlessly provides an interactive demo simulation mode.
 */

return [
    'google' => [
        'client_id'     => '', // e.g. '123456789-abc.apps.googleusercontent.com'
        'client_secret' => '', // e.g. 'GOCSPX-xxxxxxxxxxxx'
        'redirect_uri'  => 'http://localhost/WEBPAGE/eduMate/oauth.php?provider=google',
    ],
    'facebook' => [
        'app_id'        => '', // e.g. '123456789012345'
        'app_secret'    => '', // e.g. 'xxxxxxxxxxxxxxxxxxxxxxxx'
        'redirect_uri'  => 'http://localhost/WEBPAGE/eduMate/oauth.php?provider=facebook',
    ],
];

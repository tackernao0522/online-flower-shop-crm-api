<?php
// api/src/config/aws.php

return [
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
    'region' => env('AWS_REGION', 'ap-northeast-1'),
    'version' => 'latest',
];

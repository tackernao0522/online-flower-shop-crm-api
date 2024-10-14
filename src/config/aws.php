<?php

/**
 * AWS SDK configuration file
 * 
 * @see https://github.com/aws/aws-sdk-php-laravel
 */

use Aws\Laravel\AwsServiceProvider;

$awsServiceProviderVersion = 'unknown';
if (class_exists(AwsServiceProvider::class)) {
    $awsServiceProviderVersion = AwsServiceProvider::VERSION;
}

return [
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
    'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
    'version' => 'latest',
    'ua_append' => [
        'L5MOD/' . $awsServiceProviderVersion,
    ],
];

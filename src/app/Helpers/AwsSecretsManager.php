<?php

namespace App\Helpers;

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class AwsSecretsManager
 * @package App\Helpers
 */
class AwsSecretsManager
{
    /**
     * Get a secret from AWS Secrets Manager.
     *
     * @param string $secretName The name of the secret to retrieve.
     * @return array|null The secret value as an associative array, or null if retrieval fails.
     */
    public static function getSecret(string $secretName): ?array
    {
        try {
            /** @var SecretsManagerClient $client */
            $client = new SecretsManagerClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
            ]);

            $result = $client->getSecretValue([
                'SecretId' => $secretName,
            ]);
        } catch (AwsException $e) {
            Log::error('Failed to retrieve secret: ' . $e->getMessage());
            return null;
        }

        if (isset($result['SecretString'])) {
            return json_decode($result['SecretString'], true);
        }

        return null;
    }

    /**
     * Load secrets from AWS Secrets Manager and set them in the application configuration.
     *
     * @return void
     */
    public static function loadSecrets(): void
    {
        if (env('APP_ENV') === 'production') {
            $secrets = self::getSecret(env('AWS_SECRETS_MANAGER_SECRET_NAME'));
            if ($secrets) {
                Config::set([
                    'app.key' => $secrets['APP_KEY'],
                    'database.connections.mysql.username' => $secrets['DB_USERNAME'],
                    'database.connections.mysql.password' => $secrets['DB_PASSWORD'],
                    'pusher.app_id' => $secrets['PUSHER_APP_ID'],
                    'pusher.key' => $secrets['PUSHER_APP_KEY'],
                    'pusher.secret' => $secrets['PUSHER_APP_SECRET'],
                    'pusher.cluster' => $secrets['PUSHER_APP_CLUSTER'],
                    'jwt.secret' => $secrets['JWT_SECRET'],
                    // 必要に応じて他のシークレットを追加
                ]);

                // データベース接続をリフレッシュ
                DB::purge('mysql');
                DB::reconnect('mysql');
            }
        }
    }
}

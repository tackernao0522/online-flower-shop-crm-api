<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Aws\Credentials\CredentialProvider;
use Aws\SecretsManager\SecretsManagerClient;

class SecretsManagerServiceProvider extends ServiceProvider
{
    public function register()
    {
        // プロダクション環境でのみSecretsManagerClientを登録
        if (app()->environment('production') && class_exists(SecretsManagerClient::class)) {
            $this->app->singleton(SecretsManagerClient::class, function ($app) {
                return new SecretsManagerClient([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
                    'credentials' => CredentialProvider::env(), // 環境変数を使用して認証情報を取得
                ]);
            });
        }
    }

    public function boot()
    {
        if (app()->environment('production')) {
            $this->loadProductionSecrets();
        }
    }

    protected function loadProductionSecrets()
    {
        if (!class_exists(SecretsManagerClient::class)) {
            Log::info('AWS SDK is not installed. Using environment variables.');
            return;
        }

        try {
            $client = app(SecretsManagerClient::class);
            $secretId = sprintf('%s/production/app-secrets', config('app.name'));

            $result = $client->getSecretValue([
                'SecretId' => $secretId,
            ]);

            if (isset($result['SecretString'])) {
                $secrets = json_decode($result['SecretString'], true);

                if (!$this->validateSecrets($secrets)) {
                    Log::warning('Invalid or incomplete secrets retrieved from Secrets Manager. Using environment variables as fallback.');
                    return;
                }

                $this->updateConfigurations($secrets);
                Log::info('Successfully loaded secrets from AWS Secrets Manager');
            }
        } catch (\Exception $e) {
            Log::error('Failed to load secrets from AWS Secrets Manager: ' . $e->getMessage() . '. Using environment variables as fallback.');
        }
    }

    protected function validateSecrets(array $secrets): bool
    {
        $requiredSecrets = [
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'APP_KEY',
            'JWT_SECRET',
            'PUSHER_APP_ID',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET'
        ];

        foreach ($requiredSecrets as $key) {
            if (empty($secrets[$key])) {
                Log::warning("Missing required secret: {$key}");
                return false;
            }
        }

        return true;
    }

    protected function updateConfigurations(array $secrets): void
    {
        $configurations = [
            'database.connections.mysql.host' => 'DB_HOST',
            'database.connections.mysql.database' => 'DB_DATABASE',
            'database.connections.mysql.username' => 'DB_USERNAME',
            'database.connections.mysql.password' => 'DB_PASSWORD',
            'app.key' => 'APP_KEY',
            'jwt.secret' => 'JWT_SECRET',
            'broadcasting.connections.pusher.app_id' => 'PUSHER_APP_ID',
            'broadcasting.connections.pusher.key' => 'PUSHER_APP_KEY',
            'broadcasting.connections.pusher.secret' => 'PUSHER_APP_SECRET',
        ];

        foreach ($configurations as $configPath => $secretKey) {
            config([$configPath => $secrets[$secretKey] ?? env($secretKey)]);
        }

        $this->app['db']->purge();
    }
}

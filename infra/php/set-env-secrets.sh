#!/bin/sh

# AWSのSecrets Managerから秘密情報を取得
secrets=$(aws secretsmanager get-secret-value --secret-id ofcrm-production-secrets --region ap-northeast-1 --query SecretString --output text)

# 秘密情報を環境変数として設定
export APP_KEY=$(echo $secrets | jq -r .APP_KEY)
export DB_PASSWORD=$(echo $secrets | jq -r .DB_PASSWORD)
export JWT_SECRET=$(echo $secrets | jq -r .JWT_SECRET)
export PUSHER_APP_ID=$(echo $secrets | jq -r .PUSHER_APP_ID)
export PUSHER_APP_KEY=$(echo $secrets | jq -r .PUSHER_APP_KEY)
export PUSHER_APP_SECRET=$(echo $secrets | jq -r .PUSHER_APP_SECRET)
export PUSHER_APP_CLUSTER=$(echo $secrets | jq -r .PUSHER_APP_CLUSTER)

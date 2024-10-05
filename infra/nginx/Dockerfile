# infra/nginx/Dockerfile

FROM nginx:1.20-alpine

# 本番用のNginx設定ファイルをコピー
COPY ./infra/nginx/default.prod.conf /etc/nginx/conf.d/default.conf

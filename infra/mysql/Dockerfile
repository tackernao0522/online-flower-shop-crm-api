FROM mysql/mysql-server:8.0

ENV MYSQL_DATABASE=laravel \
    MYSQL_USER=phper \
    MYSQL_PASSWORD=secret \
    MYSQL_ROOT_PASSWORD=secret \
    TZ=Asia/Tokyo

# 認証方式をmysql_native_passwordに設定
COPY ./my.cnf /etc/my.cnf
RUN chmod 644 /etc/my.cnf

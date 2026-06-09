FROM flyway/flyway:latest

# Copy migration files
COPY mariadb/flyway/gmcore_core/sql /flyway/sql
COPY mariadb/flyway/gmcore_core/flyway.conf /flyway/conf/flyway.conf

# Flyway will run the migrate command by default (set in docker-compose)

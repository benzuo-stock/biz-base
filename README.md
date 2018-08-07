Biz Base
=========

[![Build Status](https://travis-ci.org/benzuo-stock/biz-base.svg?branch=master)](https://travis-ci.org/benzuo-stock/biz-base)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=biz-base&metric=alert_status)](https://sonarcloud.io/dashboard?id=biz-base)

A base(minimal) biz framework layer for symfony

## Runtime

 * PHP >= 7.1

## Config

biz.yml in Symfony-base project
```
parameters:
    biz_config:
        debug: "%kernel.debug%"
        db.options: "%biz_db_options%"
        root_directory: "%kernel.root_dir%/../"
        data_directory: "%app.data_directory%"
        cache_directory: "%kernel.cache_dir%"
        log_directory: "%kernel.logs_dir%"
        kernel.root_dir: "%kernel.root_dir%"

    biz_db_options:
        dbname: "%database_name%"
        user: "%database_user%"
        password: "%database_password%"
        host: "%database_host%"
        port: "%database_port%"
        driver: "%database_driver%"
        charset: UTF8

services:
    biz:
        class: Benzuo\Biz\Base\Context\Biz
        arguments: ["%biz_config%"]
        public: true
```
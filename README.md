# KVAZAR Index Crawler

## Compatible

* [webapp](https://github.com/kvazar-network/webapp)
* [geminiapp](https://github.com/kvazar-network/geminiapp)

## Install

* `apt install git composer manticore php-fpm php-curl php-mbstring php-pdo php-bcmath`
* `git clone https://github.com/kvazar-network/crawler.git`
* `cd crawler`
* `composer update`

## Setup

* `cp example/config.json config.json`
* `crontab -e`:`* * * * * php src/index.php`
  * drop index: `php src/index.php drop`
  * optimize index: `php src/index.php optimize`

_To prevent data lose on server failures, set [binlog flush strategy](https://manual.manticoresearch.com/Logging/Binary_logging#Binary-flushing-strategies) to `binlog_flush = 1`_
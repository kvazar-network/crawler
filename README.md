# KVAZAR Index Crawler

## Compatible

* [webapp](https://github.com/kvazar-network/webapp)

## Install

* `apt install git composer manticore php-fpm php-curl php-mbstring php-pdo php-bcmath`
* `git clone https://github.com/kvazar-network/crawler.git`
* `cd crawler`
* `composer update`
* `cp example/config.json config.json`
* `crontab -e`:`* * * * * php src/index.php`
  * drop index: `php src/index.php drop`
  * optimize index: `php src/index.php optimize`
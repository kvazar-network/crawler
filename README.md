# KVAZAR Index Crawler

## Compatible

* [webapp](https://github.com/kvazar-network/webapp)

## Install

* `git clone https://github.com/kvazar-network/crawler.git`
* `cd crawler`
* `composer update`
* `cp example/config.json config.json`
* `crontab -e`:`* * * * * php kvazar/crawler/src/index.php`
  * drop index: `php kvazar/crawler/src/index.php drop`
  * optimize index: `php kvazar/crawler/src/index.php optimize`
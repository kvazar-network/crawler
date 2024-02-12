# KVAZAR Index Crawler

## Compatible

* [webapp](https://github.com/kvazar-network/webapp)

## Install

* `composer create-project kvazar/crawler crawler`
* `cd crawler`
* `cp example/config.json config.json`
* `crontab -e`:`* * * * * php kvazar/crawler/src/index.php`
  * drop index: `php kvazar/crawler/src/index.php drop`
  * optimize index: `php kvazar/crawler/src/index.php optimize`
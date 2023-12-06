# PHP meta crawler bot

A PHP CLI mini-crawler to extract title and meta properties from a website to a CSV file.

Crawling pages is a slow task, so this script will probably fail in your browser because of `max_execution_time` quota.
It is recommended to run the script with the command line interface (CLI).


# Usage

```
php meta-crawl-bot.php italic.fr
```


# Config

Copy [config.php](config.php) and rename it to `config.php` and customize your crawler!


# Output

A folder will be created at the root with the domain name and the date of the crawl.
- 404_urls.csv: list of 404 pages
- crawled_data.csv: list of meta tags per page
- external_urls.csv: list of non-parsed external links.

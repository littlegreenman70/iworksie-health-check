# iWorks.ie Health Check

Free, read-only WordPress health check plugin for [wordpress.org](https://wordpress.org/plugins/iworksie-health-check/). Built on [`iworks-tools-core`](https://github.com/littlegreenman70/iworks-tools-core).

## Requirements

- WordPress 6.5+
- PHP 7.4+
- Composer (development)

## Local development

```bash
composer install
ln -sfn "$(pwd)" /opt/homebrew/var/www/www.iworks.new/wp-content/plugins/iworksie-health-check
wp plugin activate iworksie-health-check --path=/opt/homebrew/var/www/www.iworks.new
```

Core is resolved from the local path `../iworks-tools-core` and, for CI, from the private GitHub VCS repo. CI needs repository secret `CORE_REPO_TOKEN` (a GitHub PAT that can read `littlegreenman70/iworks-tools-core`) exported as `COMPOSER_AUTH`.

```bash
composer lint
composer analyse
composer test:unit
bin/install-wp-tests.sh iworks_tests_hc root '' 127.0.0.1 latest
composer test:integration
composer build
```

## WP-CLI

```bash
wp iworksie-health run
wp iworksie-health run --format=json
```

## License

GPL-2.0-or-later

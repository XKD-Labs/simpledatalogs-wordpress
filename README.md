# simpledatalogs for WordPress

The WordPress plugin for [simpledatalogs](https://simpledatalogs.com): cookie-free page views, opt-in autocapture and server-side events.
The wordpress.org listing text is in [readme.txt](readme.txt).

## Layout

| Path | What |
|---|---|
| `simpledatalogs.php` | Plugin header and bootstrap |
| `includes/` | Settings, admin page, front-end loader, server-side events, sender |
| `assets/js/simpledatalogs.min.js` | The `simpledatalogs` npm package 0.3.0 `dist/` file, unchanged |
| `assets/js/simpledatalogs-init.js` | Applies the settings to the SDK client |
| `tests/` | PHPUnit tests with Brain Monkey (no WordPress install needed) |

## Development (Docker only)

Nothing is installed on the host. `bin/docker` runs Composer, PHPUnit and PHPCS in the official `composer` image:

```sh
bin/docker install   # composer install
bin/docker test      # PHPUnit
bin/docker lint      # PHPCS with WordPress Coding Standards
```

## Updating the bundled SDK

1. Copy `dist/simpledatalogs.min.js` from the published npm version into `assets/js/`.
2. Update `SIMPLEDATALOGS_SDK_VERSION` in `simpledatalogs.php`, and the version in `readme.txt` and this file.

## Releasing to wordpress.org

`.github/workflows/deploy.yml` deploys a tag to the plugin's SVN repository with [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy).
It runs only when the repository variable `ENABLE_WPORG_DEPLOY` is `true`, and needs the `SVN_USERNAME` and `SVN_PASSWORD` secrets.
The tag must equal the plugin `Version` and readme `Stable tag`. Files in `.distignore` are left out; banners and icons go in `.wordpress-org/`.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE). Security reports: [SECURITY.md](SECURITY.md).

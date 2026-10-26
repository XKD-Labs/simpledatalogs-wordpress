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

Releases go to the plugin's SVN repository from a maintainer's machine; nothing runs on GitHub.
The version must equal the plugin `Version` and readme `Stable tag`. Files in `.distignore` are left out; banners and icons go in `.wordpress-org/`.

```sh
svn checkout https://plugins.svn.wordpress.org/simpledatalogs /tmp/simpledatalogs-svn
rsync -a --delete --exclude-from=.distignore ./ /tmp/simpledatalogs-svn/trunk/
rsync -a --delete .wordpress-org/ /tmp/simpledatalogs-svn/assets/
cd /tmp/simpledatalogs-svn && svn add --force trunk assets && svn copy trunk tags/0.1.0
svn commit -m "Release 0.1.0"
```

## License

GPL-2.0-or-later, see [LICENSE](LICENSE). Security reports: [SECURITY.md](SECURITY.md).

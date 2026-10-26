# Changelog

All notable changes to this plugin are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [0.1.0]

### Added

- Declares WooCommerce order tables (HPOS) compatibility, so WooCommerce no longer lists the plugin as incompatible.
- Settings → simpledatalogs page (Settings API, `manage_options`, nonces, sanitized options): public key, ingest host override, page views, opt-in autocapture, Do Not Track, excluded roles, server-side events.
- Bundled browser SDK `simpledatalogs` 0.3.0 (`assets/js/simpledatalogs.min.js`, unchanged from npm), loaded deferred on public pages only.
- Server-side events with the public key: `user_registered`, `user_logged_in`, `comment_posted`, and with WooCommerce `order_completed` and `add_to_cart`; `event_id` and `ts` set when each happens, sent in one non-blocking batch per request.
- "Send test event" button that sends `setup_test` and shows the answer.
- `uninstall.php` removing the option (every site on a network).
- PHPUnit tests with Brain Monkey and PHPCS with WordPress Coding Standards, run locally.

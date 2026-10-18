=== simpledatalogs ===
Contributors: xkdlabs
Tags: analytics, page views, events, woocommerce, privacy
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie-free page views, opt-in autocapture and server-side events for simpledatalogs.

== Description ==

simpledatalogs is a simple home for your product events and logs. This plugin connects a WordPress site to it in a minute.

* **Page views** on every public page, with no cookies.
* **Autocapture** (opt-in): page views, sessions and JavaScript errors.
* **Server-side events**: user registered, user logged in, comment posted, and with WooCommerce, order completed and add to cart.
* **Respects privacy signals**: Do Not Track and Global Privacy Control turn tracking off.
* **Excludes roles**: administrators are not tracked by default.
* **Never tracks wp-admin.**
* **Send test event** button to check the key in one click.

The browser SDK is bundled with the plugin; nothing is loaded from a CDN.

= Your own events =

Your theme can send its own events through the global client:

`simpledatalogs.track('newsletter_signup', { form: 'footer' })`

== External services ==

This plugin sends data to simpledatalogs (https://simpledatalogs.com), a hosted analytics and log service run by XKD Labs. Nothing is sent until you save a public key.

* **What is sent from visitors' browsers:** page views (page path and title), and with autocapture on, sessions, the referrer, UTM tags and JavaScript errors. Each event carries a random id and a time. No cookies are set.
* **What is sent from your server:** the server-side events you turn on, with a role, post type, product id, quantity, order total, currency, item count or payment method. No names, emails or IP addresses are put in events.
* **When:** on page loads for visitors who are not excluded and send no Do Not Track signal, when the actions above happen, and when you press "Send test event".
* **Where:** `https://edge.simpledatalogs.com`, or the ingest host you set.

Terms of service: https://simpledatalogs.com/terms
Privacy policy: https://simpledatalogs.com/privacy

== Installation ==

1. Install and activate the plugin.
2. Open **Settings → simpledatalogs**.
3. Paste your project's public key (`sdl_pub_…`) and save.
4. Press **Send test event**; a success notice means you are connected.

== Frequently Asked Questions ==

= Does it set cookies? =

No. Autocapture keeps its session id in the tab's session storage.

= Can I use a secret key? =

No. Secret keys (`sdl_sec_…`) must never appear in web pages, so the settings page refuses them.

= Are administrators tracked? =

Not by default. Pick the roles to exclude on the settings page.

= Where is the source of the bundled script? =

`assets/js/simpledatalogs.min.js` is version 0.3.0 of the `simpledatalogs` npm package, copied unchanged. Its readable source is at https://github.com/XKD-Labs/simpledatalogs-js and in the npm package (https://www.npmjs.com/package/simpledatalogs).

= Does it work with page caching? =

Yes. The script reads its settings from the page, and the privacy checks run in the browser. Role exclusion applies to logged-in users, whose pages caches usually skip.

== Changelog ==

= 0.1.0 =
* First release: settings page, bundled SDK 0.3.0, page views, opt-in autocapture, Do Not Track, role exclusion, server-side events and the test button.

== Upgrade Notice ==

= 0.1.0 =
First release.

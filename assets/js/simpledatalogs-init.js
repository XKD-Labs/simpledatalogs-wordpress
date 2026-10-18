/**
 * Applies the WordPress settings to the simpledatalogs client the bundled SDK started.
 * Runs deferred, right after the SDK, and never throws.
 */
( function () {
	// The settings printed by the plugin, and the client the SDK script tag exposes.
	var config = window.simpledatalogsWordPress || {};
	var client = window.simpledatalogs;

	// Without the client there is nothing to configure.
	if ( ! client || typeof client.track !== 'function' ) {
		return;
	}

	// Reads Do Not Track and Global Privacy Control the way the SDK does.
	function isPrivacySignalOn() {
		var browserNavigator = window.navigator || {};

		return (
			browserNavigator.doNotTrack === '1' ||
			browserNavigator.doNotTrack === 'yes' ||
			window.doNotTrack === '1' ||
			browserNavigator.globalPrivacyControl === true
		);
	}

	try {
		// With the signal on, opt the whole page out, including the site's own calls.
		if ( config.respectDoNotTrack && isPrivacySignalOn() ) {
			client.setEnabled( false );
			return;
		}

		// Autocapture already sends page views; otherwise send one for this load.
		if ( config.pageViews && ! config.autocapture ) {
			var pageViewProps = {
				path: window.location.pathname,
				title: document.title,
			};

			client.track( 'page_view', pageViewProps );
		}
	} catch ( ignoredError ) {
		// Analytics must never break the page.
	}
} )();

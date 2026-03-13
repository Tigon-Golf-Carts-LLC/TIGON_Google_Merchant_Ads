/**
 * TIGON Merchant Feeds — Admin JS
 */
(function ($) {
	'use strict';

	$(document).ready(function () {

		// Copy feed URL to clipboard.
		$('.tmf-copy-btn').on('click', function () {
			var url = $(this).data('url');
			if (!url) {
				var input = $(this).closest('tr').find('.tmf-feed-url-input');
				url = input.val();
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () {
					showTooltip('Feed URL copied to clipboard!');
				});
			} else {
				// Fallback for older browsers.
				var temp = $('<input>');
				$('body').append(temp);
				temp.val(url).select();
				document.execCommand('copy');
				temp.remove();
				showTooltip('Feed URL copied to clipboard!');
			}
		});

		// Select all text on click for feed URL inputs.
		$('.tmf-feed-url-input').on('click', function () {
			$(this).select();
		});

		/**
		 * Show a temporary tooltip notification.
		 */
		function showTooltip(message) {
			var existing = $('.tmf-copied-tooltip');
			if (existing.length) {
				existing.remove();
			}

			var tooltip = $('<div class="tmf-copied-tooltip">' + message + '</div>');
			$('body').append(tooltip);

			setTimeout(function () {
				tooltip.fadeOut(300, function () {
					$(this).remove();
				});
			}, 2000);
		}
	});
})(jQuery);

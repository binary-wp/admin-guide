/**
 * Guide Builder — Editor Page JS.
 * Placeholder palette with click-to-insert and drag & drop into TinyMCE.
 */
(function ($) {
	'use strict';

	var $palette = $('#guide-placeholder-list');

	function init() {
		buildPalette();

		// jQuery UI Tooltip on placeholder items.
		$palette.tooltip({
			items: '.guide-placeholder-item[title]',
			position: { my: 'left+10 center', at: 'right center' },
			show: { delay: 200, duration: 150 },
			hide: { duration: 100 }
		});

		// Make palette items draggable.
		$palette.on('mouseenter', '.guide-placeholder-item', function () {
			this.setAttribute('draggable', 'true');
		});
		$palette.on('dragstart', '.guide-placeholder-item', function (e) {
			e.originalEvent.dataTransfer.setData('text/plain', $(this).data('token'));
			e.originalEvent.dataTransfer.effectAllowed = 'copy';
		});

		// Click placeholder to insert into TinyMCE.
		$palette.on('click', '.guide-placeholder-item', function () {
			var token = $(this).data('token');
			if (typeof tinymce !== 'undefined' && tinymce.get('guide_content')) {
				var editor = tinymce.get('guide_content');
				editor.insertContent(tokenToPill(token));
				editor.focus();
			}
		});

		// Set up TinyMCE drop handler.
		initTinyMCEDrop();
	}

	function initTinyMCEDrop() {
		var attempts = 0;
		var interval = setInterval(function () {
			attempts++;
			if (attempts > 50) { clearInterval(interval); return; }

			if (typeof tinymce === 'undefined' || !tinymce.get('guide_content')) return;
			var editor = tinymce.get('guide_content');
			if (!editor.initialized) return;

			clearInterval(interval);

			editor.on('dragover', function (e) {
				e.preventDefault();
			});

			editor.on('drop', function () {
				setTimeout(function () { convertRawTokens(editor); }, 50);
			});
		}, 200);
	}

	function buildPalette() {
		var byGroup = {};
		$.each(guideEditor.placeholders || [], function (i, ph) {
			if (!byGroup[ph.group]) byGroup[ph.group] = [];
			byGroup[ph.group].push(ph);
		});

		var groups = Object.keys(byGroup).sort(function (a, b) {
			if (a === 'WordPress') return -1;
			if (b === 'WordPress') return 1;
			if (a === 'Custom') return 1;
			if (b === 'Custom') return -1;
			return a.localeCompare(b);
		});

		$.each(groups, function (i, group) {
			var items = byGroup[group];
			if (!items || !items.length) return;

			var $section = $('<div class="guide-placeholder-tier">');
			var $header = $('<h4 class="guide-placeholder-group-toggle">'
				+ '<span class="dashicons dashicons-arrow-down-alt2"></span> '
				+ group + ' <span class="guide-placeholder-count">(' + items.length + ')</span>'
				+ '</h4>');
			var $list = $('<div class="guide-placeholder-group-items">');

			if (i > 0) {
				$list.hide();
				$header.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
			}

			$header.on('click', function () {
				$list.slideToggle(150);
				$(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-right-alt2');
			});

			$.each(items, function (j, ph) {
				$list.append(
					$('<div class="guide-placeholder-item" title="' + (ph.description || ph.token) + '" data-token="' + ph.token + '">'
						+ '<code>' + ph.token + '</code></div>')
				);
			});

			$section.append($header).append($list);
			$palette.append($section);
		});
	}

	function tokenToPill(token) {
		return '<span class="mceNonEditable guide-ph" data-ph="' + token + '" contenteditable="false">' + token + '</span>';
	}

	function convertRawTokens(editor) {
		var body = editor.getBody();
		var html = body.innerHTML;

		var replaced = html.replace(
			/(<span[^>]*guide-ph[^>]*>.*?<\/span>)|\{\{([a-z_]+)\}\}/g,
			function (match, pill, token) {
				if (pill) return pill;
				return tokenToPill('{{' + token + '}}');
			}
		);

		if (replaced !== html) {
			var bookmark = editor.selection.getBookmark(2, true);
			body.innerHTML = replaced;
			try { editor.selection.moveToBookmark(bookmark); } catch (e) {}
			editor.nodeChanged();
		}
	}

	$(document).ready(init);

})(jQuery);

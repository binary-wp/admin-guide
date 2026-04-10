/**
 * Guide Builder — Single-page builder.
 * Sortable tabs, accordion editors, placeholder palette, AJAX save.
 */
(function ($) {
	'use strict';

	var $sortable   = $('#guide-tabs-sortable');
	var $palette    = $('#guide-placeholder-list');
	var $saveAll    = $('#guide-builder-save-all');
	var $saveStatus = $('#guide-builder-save-status');
	var activeEditor = null;
	var isDirty = false;

	// ── Init ────────────────────────────────────────────────────────────

	function init() {
		populateSelects();
		initSortable();
		buildPalette();
		bindEvents();
		checkExternalStatus();
		initAllEditors();
	}

	// ── System Select ───────────────────────────────────────────────────

	function populateSelects() {
		var $sel = $('#guide-add-system');

		$.each(guideBuilder.systemTabs || [], function (i, group) {
			var $optgroup = $('<optgroup label="' + group.group + '">');
			$.each(group.items, function (j, item) {
				var display = item.label + (item.source === 'post_type' || item.source === 'taxonomy' ? ' (' + item.slug + ')' : '');
				var $opt = $('<option value="' + item.slug + '" data-label="' + item.label + '" data-source="' + item.source + '">' + display + '</option>');
				if (parseInt(item.disabled)) {
					$opt.prop('disabled', true);
				}
				$optgroup.append($opt);
			});
			$sel.append($optgroup);
		});
	}

	// ── Sortable ────────────────────────────────────────────────────────

	function initSortable() {
		$sortable.sortable({
			handle: '.guide-tab-handle',
			placeholder: 'guide-tab-placeholder',
			update: function () { markDirty(); }
		});
	}

	function markDirty() {
		isDirty = true;
		$saveAll.show();
		$saveStatus.text('');
	}

	function saveOrder(callback) {
		var slugs = [];
		$sortable.find('li').each(function () { slugs.push($(this).data('slug')); });
		$.post(guideBuilder.ajaxUrl, { action: guideBuilder.actions.reorder, nonce: guideBuilder.nonce, slugs: slugs }, callback);
	}

	// ── Events ──────────────────────────────────────────────────────────

	function bindEvents() {
		// Accordion toggle.
		$sortable.on('click', '.guide-tab-header', function (e) {
			if ($(e.target).closest('.guide-tab-remove').length) return;
			var $li = $(this).closest('li');
			toggleEditor($li);
		});

		// Per-tab save button.
		$sortable.on('click', '.guide-tab-save', function () {
			var slug = $(this).data('slug');
			saveTab(slug, $(this).closest('li'));
		});

		// Label change → mark dirty + update header live.
		$sortable.on('input', '.guide-tab-meta-label', function () {
			var $li = $(this).closest('li');
			$li.find('.guide-tab-label').text($(this).val());
			markDirty();
		});

		// Save All button.
		$saveAll.on('click', function () {
			$saveStatus.text('Saving...');
			saveOrder(function () {
				isDirty = false;
				$saveAll.hide();
				$saveStatus.text('Saved ✓');
				setTimeout(function () { $saveStatus.text(''); }, 2000);
			});
		});

		// Remove button.
		$sortable.on('click', '.guide-tab-remove', function (e) {
			e.stopPropagation();
			var slug = $(this).data('slug');
			if (!confirm('Remove guide "' + slug + '"?')) return;
			removeTab(slug, $(this).closest('li'));
		});

		// System add button.
		$('#guide-add-system-btn').on('click', function () {
			var $sel    = $('#guide-add-system');
			var $opt    = $sel.find(':selected');
			var slug    = $sel.val();
			if (!slug) return;
			var label   = $opt.data('label') || $opt.text().replace(/\s*\(.+\)$/, '');
			var source  = $opt.data('source') || 'custom';
			addTab(slug, label, source);
		});
		$('#guide-add-custom-btn').on('click', function () {
			var slug = $('#guide-add-custom-slug').val().trim();
			var label = $('#guide-add-custom-label').val().trim();
			if (!slug || !label) return;
			addTab(slug, label, 'custom');
		});

		// Make placeholder items draggable.
		$palette.on('mouseenter', '.guide-placeholder-item', function () {
			this.setAttribute('draggable', 'true');
		});
		$palette.on('dragstart', '.guide-placeholder-item', function (e) {
			e.originalEvent.dataTransfer.setData('text/plain', $(this).data('token'));
			e.originalEvent.dataTransfer.effectAllowed = 'copy';
		});

		// Click placeholder to insert.
		$palette.on('click', '.guide-placeholder-item', function () {
			if (!activeEditor) return;
			var editor = tinymce.get('guide-editor-' + activeEditor);
			if (editor) {
				editor.insertContent(tokenToPill($(this).data('token')));
				editor.focus();
			}
		});
	}

	// ── Init All Editors ────────────────────────────────────────────────

	function initSingleEditor(editorId) {
		if (typeof tinymce === 'undefined') return;
		// Don't re-init if already exists.
		if (tinymce.get(editorId)) return;

		tinymce.init({
			selector: '#' + editorId,
			menubar: false,
			toolbar: 'formatselect | bold italic | bullist numlist | link image',
			plugins: 'lists link image',
			content_css: guideBuilder.tinymceCss || '',
			height: 350,
			setup: function (editor) {
				editor.on('drop', function () {
					setTimeout(function () { convertRawTokens(editor); }, 50);
				});
				editor.on('dragover', function (e) { e.preventDefault(); });
				editor.on('focus', function () {
					activeEditor = editorId.replace('guide-editor-', '');
				});
			}
		});
	}

	function initAllEditors() {
		$sortable.find('.guide-tab-textarea').each(function () {
			initSingleEditor(this.id);
		});
	}

	// ── Accordion ───────────────────────────────────────────────────────

	function toggleEditor($li) {
		var $editor = $li.find('.guide-tab-editor');
		var $toggle = $li.find('.guide-tab-toggle');

		if ($editor.is(':visible')) {
			$editor.slideUp(200);
			$toggle.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
			return;
		}

		// Collapse any other open editor.
		$sortable.find('.guide-tab-editor:visible').each(function () {
			$(this).slideUp(200);
			$(this).closest('li').find('.guide-tab-toggle')
				.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		});

		$toggle.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		$editor.slideDown(200);
		activeEditor = $li.data('slug');
	}

	// ── Save Tab ────────────────────────────────────────────────────────

	function saveTab(slug, $li) {
		var editorId = 'guide-editor-' + slug;
		var editor   = (typeof tinymce !== 'undefined') ? tinymce.get(editorId) : null;
		var content  = editor ? editor.getContent() : $('#' + editorId).val();
		var newLabel = $li.find('.guide-tab-meta-label').val().trim();
		var newSlug  = $li.find('.guide-tab-meta-slug').val().trim();

		var $status = $li.find('.guide-tab-save-status');
		$status.text('Saving...');

		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.saveTab,
			nonce: guideBuilder.nonce,
			slug: slug,
			new_slug: newSlug,
			new_label: newLabel,
			content: content
		}, function (response) {
			if (response.success) {
				$status.text('Saved ✓');

				// Update UI if slug/label changed.
				if (response.data && response.data.slug && response.data.slug !== slug) {
					$li.data('slug', response.data.slug);
					$li.find('.guide-tab-save').data('slug', response.data.slug);
				}
				if (response.data && response.data.label) {
					$li.find('.guide-tab-label').text(response.data.label);
				}
			} else {
				$status.text('Error');
			}
			setTimeout(function () { $status.text(''); }, 2000);
		});
	}

	// ── Add / Remove Tabs ───────────────────────────────────────────────

	function addTab(slug, label, source) {
		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.addTab,
			nonce: guideBuilder.nonce,
			slug: slug, label: label, source: source
		}, function (response) {
			if (!response.success) { alert(response.data || 'Error'); return; }

			var slugReadonly = (source !== 'custom') ? ' readonly' : '';
			var $li = $('<li data-slug="' + slug + '" data-source="' + source + '" class="guide-tab-item">'
				+ '<div class="guide-tab-header">'
				+ '<span class="guide-tab-handle dashicons dashicons-menu"></span>'
				+ '<span class="guide-tab-label">' + label + '</span>'
				+ '<span class="guide-tab-source">' + source + '</span>'
				+ '<span class="guide-tab-toggle dashicons dashicons-arrow-right-alt2"></span>'
				+ '<button type="button" class="guide-tab-remove button-link" data-slug="' + slug + '" title="Remove">'
				+ '<span class="dashicons dashicons-no-alt"></span></button>'
				+ '</div>'
				+ '<div class="guide-tab-editor" style="display:none">'
				+ '<div class="guide-tab-meta">'
				+ '<label>Label: <input type="text" class="guide-tab-meta-label regular-text" value="' + label + '"></label>'
				+ '<label>Slug: <input type="text" class="guide-tab-meta-slug" value="' + slug + '" style="width:160px"' + slugReadonly + '></label>'
				+ '</div>'
				+ '<div class="guide-tab-editor-area"><textarea class="guide-tab-textarea" id="guide-editor-' + slug + '"></textarea></div>'
				+ '<div class="guide-tab-actions">'
				+ '<button type="button" class="button button-primary guide-tab-save" data-slug="' + slug + '">Save</button>'
				+ '<span class="guide-tab-save-status"></span></div></div></li>');

			$sortable.append($li);
			saveOrder();

			// Init TinyMCE for the new tab.
			initSingleEditor('guide-editor-' + slug);

			// Disable in system select + clean custom fields.
			$('#guide-add-system').find('option[value="' + slug + '"]').prop('disabled', true);
			$('#guide-add-system').val('');
			$('#guide-add-custom-slug, #guide-add-custom-label').val('');
		});
	}

	function removeTab(slug, $li) {
		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.removeTab,
			nonce: guideBuilder.nonce,
			slug: slug
		}, function (response) {
			if (response.success) {
				// Destroy TinyMCE instance if exists.
				var editorId = 'guide-editor-' + slug;
				if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
					tinymce.get(editorId).remove();
				}
				$li.fadeOut(200, function () { $(this).remove(); });
				if (activeEditor === slug) activeEditor = null;

				// Re-enable in system select.
				$('#guide-add-system').find('option[value="' + slug + '"]').prop('disabled', false);
			}
		});
	}

	// ── External Status Checker ─────────────────────────────────────────

	function checkExternalStatus() {
		var $targets = $('.guide-external-status');
		if (!$targets.length) return;

		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.checkStatus,
			nonce: guideBuilder.nonce
		}, function (response) {
			if (!response.success) return;

			$.each(response.data, function (slug, integration) {
				// Per-integration standalone placeholder.
				var $el = $targets.filter('[data-integration="' + slug + '"]');
				if ($el.length) {
					var html = '<table class="widefat fixed striped" style="max-width:600px"><thead><tr>'
						+ '<th>Service</th><th>Status</th><th>Details</th></tr></thead><tbody>';
					$.each(integration.services, function (i, svc) {
						html += '<tr><td><strong>' + svc.service + '</strong></td>'
							+ '<td>' + statusBadge(svc.status, svc.message) + '</td>'
							+ '<td>' + (svc.message || '—') + '</td></tr>';
					});
					html += '</tbody></table>';
					$el.html(html);
				}

				// Combined table: fill in status cells.
				$.each(integration.services, function (i, svc) {
					var $row = $('tr[data-integration="' + slug + '"][data-service-index="' + i + '"]');
					if ($row.length) {
						$row.find('.guide-status-cell').html(
							statusBadge(svc.status, svc.message)
						);
					}
				});
			});
		});
	}

	function statusBadge(status, message) {
		var colors = {
			'ok':      { bg: '#edfaef', color: '#00a32a', border: '#00a32a' },
			'warning': { bg: '#fff8e5', color: '#996800', border: '#dba617' },
			'error':   { bg: '#fcf0f1', color: '#d63638', border: '#d63638' },
			'unknown': { bg: '#f0f0f1', color: '#8c8f94', border: '#c3c4c7' }
		};
		var c = colors[status] || colors['unknown'];
		var label = message || status;
		return '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;'
			+ 'background:' + c.bg + ';color:' + c.color + ';border:1px solid ' + c.border + '">'
			+ '● ' + label + '</span>';
	}

	// ── Placeholder Palette ─────────────────────────────────────────────

	function buildPalette() {
		var byGroup = {};
		$.each(guideBuilder.placeholders || [], function (i, ph) {
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

		// Init tooltips.
		$palette.tooltip({
			items: '.guide-placeholder-item[title]',
			position: { my: 'left+10 center', at: 'right center' },
			show: { delay: 200, duration: 150 },
			hide: { duration: 100 }
		});
	}

	// ── Helpers ──────────────────────────────────────────────────────────

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

	// ── Boot ────────────────────────────────────────────────────────────

	$(document).ready(init);

})(jQuery);

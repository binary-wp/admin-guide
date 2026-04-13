/**
 * Guide Builder — Nav-menu style hierarchical sortable.
 */
(function ($) {
	'use strict';

	var $sortable = $('#guide-sortable');
	var $palette  = $('#guide-placeholder-list');
	var $saveBtn  = $('#guide-save-order');
	var $saveStatus = $('#guide-save-status');
	var maxDepth  = parseInt(guideBuilder.maxDepth) || 1;
	var depthPx   = 30;
	var isDirty   = false;

	// ── Init ────────────────────────────────────────────────────────────

	function init() {
		populateSystemSelect();
		buildPalette();
		initSortable();
		bindEvents();
	}

	// ── System Select ───────────────────────────────────────────────────

	function populateSystemSelect() {
		var $sel = $('#guide-add-system');
		$.each(guideBuilder.systemTabs || [], function (i, group) {
			var $optgroup = $('<optgroup label="' + group.group + '">');
			$.each(group.items, function (j, item) {
				var display = item.label + (item.source === 'post_type' || item.source === 'taxonomy' ? ' (' + item.slug + ')' : '');
				var $opt = $('<option value="' + item.slug + '" data-label="' + item.label + '" data-source="' + item.source + '">' + display + '</option>');
				if (parseInt(item.disabled)) $opt.prop('disabled', true);
				$optgroup.append($opt);
			});
			$sel.append($optgroup);
		});
	}

	// ── Sortable with Depth ─────────────────────────────────────────────

	function initSortable() {
		var startDepth, startX;

		$sortable.sortable({
			handle: '.menu-item-handle',
			placeholder: 'sortable-placeholder',
			tolerance: 'pointer',
			start: function (e, ui) {
				startDepth = getDepth(ui.item);
				startX = e.pageX;
				ui.placeholder.height(ui.item.outerHeight());
				ui.placeholder.css('margin-left', (startDepth * depthPx) + 'px');
			},
			sort: function (e, ui) {
				var offset = e.pageX - startX;
				var depthChange = Math.round(offset / depthPx);
				var newDepth = Math.max(0, Math.min(maxDepth, startDepth + depthChange));

				// Can only nest under the item directly above.
				var $prev = ui.placeholder.prev('li:not(.ui-sortable-helper)');
				if (newDepth > 0 && !$prev.length) {
					newDepth = 0;
				}
				if ($prev.length) {
					var prevDepth = getDepth($prev);
					if (newDepth > prevDepth + 1) {
						newDepth = prevDepth + 1;
					}
				}

				ui.item.data('new-depth', newDepth);
				ui.placeholder.css('margin-left', (newDepth * depthPx) + 'px');
			},
			stop: function (e, ui) {
				var newDepth = parseInt(ui.item.data('new-depth'));
				if (isNaN(newDepth)) newDepth = startDepth;

				setDepth(ui.item, newDepth);
				markDirty();
			}
		});
	}

	function getDepth($el) {
		return parseInt($el.data('depth')) || 0;
	}

	function setDepth($el, depth) {
		$el.data('depth', depth);
		$el.removeClass(function (i, cls) {
			return (cls.match(/menu-item-depth-\d+/g) || []).join(' ');
		}).addClass('menu-item-depth-' + depth);
	}

	function markDirty() {
		isDirty = true;
		$saveBtn.show();
		$saveStatus.text('');
	}

	function collectOrder() {
		var items = [];
		var parentStack = [0];

		$sortable.find('> li').each(function (i) {
			var $item = $(this);
			var id    = parseInt($item.data('id'));
			var depth = getDepth($item);

			while (parentStack.length - 1 > depth) {
				parentStack.pop();
			}
			var parentId = (depth > 0 && parentStack[depth]) ? parentStack[depth] : 0;
			parentStack[depth + 1] = id;

			items.push({ id: id, parent_id: parentId, position: i });
		});

		return items;
	}

	function saveOrder() {
		$saveStatus.text('Saving...');
		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.save_order,
			nonce: guideBuilder.nonce,
			items: collectOrder()
		}, function (response) {
			if (response.success) {
				isDirty = false;
				$saveBtn.hide();
				$saveStatus.text('Saved ✓');
				setTimeout(function () { $saveStatus.text(''); }, 2000);
			} else {
				$saveStatus.text('Error saving.');
			}
		});
	}

	// ── Events ──────────────────────────────────────────────────────────

	function bindEvents() {
		// Save order.
		$saveBtn.on('click', function () { saveOrder(); });

		// Toggle settings panel via arrow (WP standard: .item-edit toggles .menu-item-edit-active).
		$sortable.on('click', '.item-edit', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $li = $(this).closest('li');
			// Close others.
			$sortable.find('.menu-item-edit-active').not($li)
				.removeClass('menu-item-edit-active').addClass('menu-item-edit-inactive')
				.find('.menu-item-settings').slideUp(150);
			// Toggle this one.
			$li.toggleClass('menu-item-edit-active menu-item-edit-inactive');
			$li.find('.menu-item-settings').slideToggle(150);
		});

		// Inline label edit.
		$sortable.on('change', '.guide-inline-label', function () {
			var $li   = $(this).closest('li');
			var label = $(this).val().trim();
			if (label) {
				$li.find('.menu-item-title').text(label);
				markDirty();
			}
		});

		// Remove — two-click pattern: first click shows "confirm?", second deletes.
		$sortable.on('click', '.guide-item-delete', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $link = $(this);
			var $li   = $link.closest('li');
			var id    = $link.data('id');

			// First click: ask for confirmation inline.
			if (!$link.data('confirming')) {
				$link.data('confirming', true).text('Confirm removal').css('color', '#b32d2e');
				setTimeout(function () {
					$link.data('confirming', false).text('Remove').css('color', '');
				}, 4000);
				return;
			}

			// Second click: proceed with deletion.
			$link.text('Removing…');
			$.post(guideBuilder.ajaxUrl, {
				action: guideBuilder.actions.remove_guide,
				nonce: guideBuilder.nonce,
				id: id
			}).done(function (response) {
				if (response.success) {
					$li.fadeOut(200, function () { $(this).remove(); });
				} else {
					console.error('[GuideBuilder] remove error:', response.data);
					$link.text('Error — try again').css('color', '#b32d2e');
				}
			}).fail(function (xhr) {
				console.error('[GuideBuilder] remove AJAX failed:', xhr.status, xhr.statusText);
				$link.text('Failed — try again').css('color', '#b32d2e');
			});
		});

		// Add system.
		$('#guide-add-system-btn').on('click', function () {
			var $sel   = $('#guide-add-system');
			var $opt   = $sel.find(':selected');
			var slug   = $sel.val();
			if (!slug) return;
			addGuide(slug, $opt.data('label') || slug, $opt.data('source') || 'custom');
		});

		// Add custom.
		$('#guide-add-custom-btn').on('click', function () {
			var slug  = $('#guide-add-custom-slug').val().trim();
			var label = $('#guide-add-custom-label').val().trim();
			var group = $('#guide-add-custom-group').is(':checked') ? 1 : 0;
			if (!slug || !label) return;
			addGuide(slug, label, 'custom', group);
		});

		// Import toggle.
		$('#guide-import-btn').on('click', function () {
			$('#guide-import-form').toggle();
		});
	}

	function addGuide(slug, label, source, group) {
		$.post(guideBuilder.ajaxUrl, {
			action: guideBuilder.actions.add_guide,
			nonce: guideBuilder.nonce,
			slug: slug, label: label, source: source, group: group || 0
		}, function (response) {
			if (!response.success) { alert(response.data || 'Error'); return; }

			var d = response.data;
			var $li = buildMenuItem(d.id, d.label, d.source, guideBuilder.editUrl + d.id, 0);
			$sortable.append($li);

			$('#guide-add-system').find('option[value="' + slug + '"]').prop('disabled', true).end().val('');
			$('#guide-add-custom-slug, #guide-add-custom-label').val('');

			markDirty();
		});
	}

	function buildMenuItem(id, label, source, editUrl, depth) {
		return $('<li id="guide-item-' + id + '" class="menu-item menu-item-depth-' + depth + ' menu-item-edit-inactive" data-id="' + id + '" data-depth="' + depth + '">'
			+ '<div class="menu-item-bar"><div class="menu-item-handle">'
			+ '<label class="item-title"><span class="menu-item-title">' + label + '</span></label>'
			+ '<span class="item-controls"><span class="item-type">' + source + '</span>'
			+ '<a class="item-edit" href="#guide-item-settings-' + id + '"><span class="screen-reader-text">Toggle</span></a>'
			+ '</span></div></div>'
			+ '<div class="menu-item-settings wp-clearfix" id="guide-item-settings-' + id + '" style="display:none">'
			+ '<p class="description description-wide"><label>Label<br>'
			+ '<input type="text" class="widefat guide-inline-label" value="' + label + '" data-id="' + id + '"></label></p>'
			+ '<div class="menu-item-actions description-wide submitbox">'
			+ '<a class="item-edit-link" href="' + editUrl + '">Edit Content</a>'
			+ '<span class="meta-sep"> | </span>'
			+ '<a class="guide-item-delete submitdelete deletion" href="#" data-id="' + id + '">Remove</a>'
			+ '</div></div>'
			+ '<ul class="menu-item-transport"></ul></li>');
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
	}

	$(document).ready(init);

})(jQuery);

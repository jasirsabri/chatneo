(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var list = document.getElementById('wcw-routes-list');
		var addRouteBtn = document.getElementById('wcw-add-route');
		var routeTemplate = document.getElementById('wcw-route-template');
		var questionTemplate = document.getElementById('wcw-question-template');

		if (!list || !addRouteBtn || !routeTemplate || !questionTemplate) {
			return;
		}

		// ---------- Cloning helpers ----------
		// Templates contain literal placeholder tokens in `name="..."`
		// attributes (e.g. wcw_routes[__ROUTE_INDEX__][label]). Cloning via
		// cloneNode keeps those tokens intact, so we clone into a scratch
		// container, string-replace the tokens, then re-parse before
		// inserting into the live form.
		function cloneWithReplacements(template, replacements) {
			var scratch = document.createElement('div');
			scratch.appendChild(template.content.cloneNode(true));
			var html = scratch.innerHTML;
			Object.keys(replacements).forEach(function (token) {
				html = html.split(token).join(replacements[token]);
			});
			scratch.innerHTML = html;
			return scratch.firstElementChild;
		}

		function nextRouteIndex() {
			var n = parseInt(list.getAttribute('data-next-route-index') || '0', 10);
			list.setAttribute('data-next-route-index', String(n + 1));
			return n;
		}

		function nextQuestionIndex(questionsList) {
			var n = parseInt(questionsList.getAttribute('data-next-q-index') || '0', 10);
			questionsList.setAttribute('data-next-q-index', String(n + 1));
			return n;
		}

		// ---------- Add / remove / reorder routes ----------
		addRouteBtn.addEventListener('click', function () {
			var index = nextRouteIndex();
			var row = cloneWithReplacements(routeTemplate, { '__ROUTE_INDEX__': String(index) });
			list.appendChild(row);
			wireRoute(row);
		});

		function wireRoute(row) {
			var removeBtn = row.querySelector('.wcw-remove-route');
			var upBtn = row.querySelector('.wcw-move-route-up');
			var downBtn = row.querySelector('.wcw-move-route-down');
			var addQuestionBtn = row.querySelector('.wcw-add-question');
			var questionsList = row.querySelector('.wcw-questions-list');
			var finalTypeRadios = row.querySelectorAll('.wcw-final-type');
			var directFields = row.querySelector('.wcw-direct-contact-fields');

			if (removeBtn) {
				removeBtn.addEventListener('click', function () {
					if (window.confirm('Remove this route?')) {
						row.remove();
					}
				});
			}

			if (upBtn) {
				upBtn.addEventListener('click', function () {
					var prev = row.previousElementSibling;
					if (prev) {
						list.insertBefore(row, prev);
					}
				});
			}

			if (downBtn) {
				downBtn.addEventListener('click', function () {
					var next = row.nextElementSibling;
					if (next) {
						list.insertBefore(next, row);
					}
				});
			}

			if (addQuestionBtn && questionsList) {
				addQuestionBtn.addEventListener('click', function () {
					var routeIndexInput = row.querySelector('.wcw-route-id');
					var routeIndex = extractRouteIndex(routeIndexInput);
					var qIndex = nextQuestionIndex(questionsList);
					var qRow = cloneWithReplacements(questionTemplate, {
						'__ROUTE_INDEX__': routeIndex,
						'__Q_INDEX__': String(qIndex),
					});
					questionsList.appendChild(qRow);
					wireQuestion(qRow, row);
					syncFieldMap(row);
				});
			}

			finalTypeRadios.forEach(function (radio) {
				radio.addEventListener('change', function () {
					if (directFields) {
						directFields.style.display = (radio.value === 'direct_message' && radio.checked) ? '' : 'none';
					}
				});
			});

			row.querySelectorAll('.wcw-question-row').forEach(function (qRow) {
				wireQuestion(qRow, row);
			});
		}

		// The route's array index lives in the `name` attribute of its
		// hidden id field (wcw_routes[N][id]) — pull N back out of that
		// rather than tracking it separately, so it always matches reality
		// even after reordering (reordering never changes array indices,
		// only DOM position, which is fine — PHP doesn't care about gaps).
		function extractRouteIndex(routeIdInput) {
			var match = /wcw_routes\[([^\]]+)\]/.exec(routeIdInput.getAttribute('name') || '');
			return match ? match[1] : '0';
		}

		function wireQuestion(qRow, routeRow) {
			var removeBtn = qRow.querySelector('.wcw-remove-question');
			var keyInput = qRow.querySelector('.wcw-question-key');
			var upBtn = qRow.querySelector('.wcw-move-question-up');
			var downBtn = qRow.querySelector('.wcw-move-question-down');

			if (removeBtn) {
				removeBtn.addEventListener('click', function () {
					qRow.remove();
					syncFieldMap(routeRow);
				});
			}

			if (keyInput) {
				keyInput.addEventListener('blur', function () {
					syncFieldMap(routeRow);
				});
			}

			// Reordering only ever swaps with a sibling inside the same
			// route's own .wcw-questions-list — a question never moves
			// into a different route. Swapping DOM position is enough:
			// browsers submit form fields in document order, so PHP
			// receives (and processes) them in this new order regardless
			// of the numeric [Q_INDEX] left in each field's name attribute.
			if (upBtn) {
				upBtn.addEventListener('click', function () {
					var prev = qRow.previousElementSibling;
					if (prev) {
						qRow.parentNode.insertBefore(qRow, prev);
					}
				});
			}

			if (downBtn) {
				downBtn.addEventListener('click', function () {
					var next = qRow.nextElementSibling;
					if (next) {
						qRow.parentNode.insertBefore(next, qRow);
					}
				});
			}
		}

		// ---------- Keep the CRM field-mapping table in sync ----------
		// Rebuilds the mapping rows to match this route's current question
		// keys (plus the built-in "name" and "label"), preserving any
		// value already typed for a key that still exists.
		function syncFieldMap(routeRow) {
			var routeIdInput = routeRow.querySelector('.wcw-route-id');
			var routeIndex = extractRouteIndex(routeIdInput);
			var tbody = routeRow.querySelector('.wcw-crm-field-map-body');
			if (!tbody) {
				return;
			}

			var existingValues = {};
			tbody.querySelectorAll('tr').forEach(function (tr) {
				var mapKey = tr.getAttribute('data-map-key');
				var input = tr.querySelector('input');
				if (mapKey && input) {
					existingValues[mapKey] = input.value;
				}
			});

			var keys = [{ key: 'name', label: 'Name' }, { key: 'label', label: 'Route label' }];
			routeRow.querySelectorAll('.wcw-question-key').forEach(function (input) {
				var k = input.value.trim();
				if (k) {
					keys.push({ key: k, label: k });
				}
			});

			tbody.innerHTML = '';
			keys.forEach(function (item) {
				var tr = document.createElement('tr');
				tr.setAttribute('data-map-key', item.key);

				var tdLabel = document.createElement('td');
				tdLabel.textContent = item.label;

				var tdInput = document.createElement('td');
				var input = document.createElement('input');
				input.type = 'text';
				input.className = 'regular-text';
				input.name = 'wcw_routes[' + routeIndex + '][crm][field_map][' + item.key + ']';
				input.value = existingValues[item.key] || '';

				tdInput.appendChild(input);
				tr.appendChild(tdLabel);
				tr.appendChild(tdInput);
				tbody.appendChild(tr);
			});
		}

		// Wire up whatever routes were already rendered server-side on load.
		list.querySelectorAll('.wcw-route-row').forEach(function (row) {
			wireRoute(row);
		});
	});
})();

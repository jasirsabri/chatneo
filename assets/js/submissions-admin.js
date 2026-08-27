(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var selectAll = document.getElementById('wcw-select-all');
		var bulkDeleteBtn = document.getElementById('wcw-bulk-delete-btn');
		var form = document.getElementById('wcw-submissions-form');

		if (!form) {
			return;
		}

		function getRowCheckboxes() {
			return form.querySelectorAll('.wcw-row-checkbox');
		}

		function updateBulkButtonState() {
			var anyChecked = Array.prototype.some.call(getRowCheckboxes(), function (cb) {
				return cb.checked;
			});
			if (bulkDeleteBtn) {
				bulkDeleteBtn.disabled = !anyChecked;
			}
		}

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				getRowCheckboxes().forEach(function (cb) {
					cb.checked = selectAll.checked;
				});
				updateBulkButtonState();
			});
		}

		form.addEventListener('change', function (e) {
			if (e.target && e.target.classList.contains('wcw-row-checkbox')) {
				if (!e.target.checked && selectAll) {
					selectAll.checked = false;
				}
				updateBulkButtonState();
			}
		});

		updateBulkButtonState();
	});
})();

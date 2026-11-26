/**
 * iWorks.ie Health Check admin helpers.
 */
(function () {
	'use strict';

	function post(action, data) {
		var body = new window.FormData();
		body.append('action', action);
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (value !== null && typeof value === 'object') {
				body.append(key, JSON.stringify(value));
			} else {
				body.append(key, value);
			}
		});
		var url =
			(window.iwiehcAdmin && window.iwiehcAdmin.ajaxUrl) ||
			(window.iwtCore && window.iwtCore.ajaxUrl) ||
			'';
		return window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json();
		});
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function progressLabel(current, total, strings) {
		var template = (strings && strings.checkingProgress) || 'Checking {current} of {total}…';
		return template.replace('{current}', String(current)).replace('{total}', String(total));
	}

	function runAllChecks() {
		var cfg = window.iwtCore;
		if (!cfg || !cfg.checks || !cfg.checks.length) {
			return;
		}
		var strings = cfg.strings || {};
		var list = document.querySelector('[data-iwt-results]');
		var button = document.querySelector('[data-iwiehc-run-checks]');
		var empty = document.querySelector('[data-iwt-empty]');
		var summaryWrap = document.querySelector('[data-iwt-summary-wrap]');
		if (!list) {
			return;
		}
		if (empty) {
			empty.hidden = true;
		}
		if (summaryWrap) {
			summaryWrap.hidden = false;
		}
		if (button) {
			button.disabled = true;
			button.innerHTML =
				'<span class="spinner is-active" aria-hidden="true"></span> ' +
				escapeHtml(strings.running || 'Running…');
		}
		list.innerHTML = '';
		list.setAttribute('aria-busy', 'true');
		var collected = [];
		var total = cfg.checks.length;
		var chain = Promise.resolve();
		var ui = window.iwtCoreUi || {};

		cfg.checks.forEach(function (check, index) {
			chain = chain.then(function () {
				if (button) {
					button.innerHTML =
						'<span class="spinner is-active" aria-hidden="true"></span> ' +
						escapeHtml(progressLabel(index + 1, total, strings));
				}
				return post(cfg.prefix + '_run_check', {
					nonce: cfg.checksNonce,
					check_id: check.id
				}).then(function (json) {
					var result =
						json && json.success
							? json.data
							: {
									status: 'critical',
									label: check.label,
									description: 'Request failed'
							  };
					result.id = check.id;
					collected.push(result);
					if (ui.renderGrouped) {
						list.innerHTML = ui.renderGrouped(collected);
						if (ui.bindAccordions) {
							ui.bindAccordions(list);
						}
						if (ui.updateSummary) {
							ui.updateSummary(list);
						}
					}
				});
			});
		});
		chain.finally(function () {
			list.removeAttribute('aria-busy');
			if (button) {
				button.disabled = false;
				button.textContent = button.getAttribute('data-label') || 'Run checks';
			}
			post('iwiehc_cache_results', {
				nonce: cfg.checksNonce
			}).then(function (json) {
				if (json && json.success && json.data && json.data.last_checked) {
					var el = document.querySelector('.iwiehc-last-checked');
					if (el) {
						el.textContent = json.data.last_checked;
					}
				}
			});
		});
	}

	function watchDiskJob(progressId) {
		var cfg = window.iwtCore;
		if (!cfg) {
			return;
		}
		var progress = document.querySelector('[data-iwt-progress="' + progressId + '"]');
		if (!progress) {
			return;
		}
		var timer = window.setInterval(function () {
			var track = progress.querySelector('[role="progressbar"]');
			var now = track ? parseInt(track.getAttribute('aria-valuenow') || '0', 10) : 0;
			var msg = progress.querySelector('.iwt-progress__message');
			var text = msg ? msg.textContent || '' : '';
			if (/cancelled/i.test(text)) {
				window.clearInterval(timer);
				return;
			}
			if (now >= 100 || /complete|done/i.test(text)) {
				window.clearInterval(timer);
				post('iwiehc_save_disk_summary', {
					nonce: cfg.jobsNonce
				}).then(function (json) {
					if (json && json.success) {
						window.location.reload();
					}
				});
			}
		}, 700);
	}

	function testEmail() {
		var btn = document.getElementById('iwiehc-send-test-email');
		var out = document.getElementById('iwiehc-test-email-result');
		if (!btn || !out) {
			return;
		}
		btn.addEventListener('click', function () {
			btn.disabled = true;
			out.textContent =
				(window.iwiehcAdmin && window.iwiehcAdmin.strings && window.iwiehcAdmin.strings.emailSending) ||
				'Sending…';
			post('iwiehc_test_email', {
				nonce: btn.getAttribute('data-nonce') || ''
			})
				.then(function (json) {
					btn.disabled = false;
					var msg =
						json && json.data && json.data.message ? json.data.message : 'Unexpected response.';
					out.className =
						'iwiehc-test-email-result notice ' +
						(json && json.success ? 'notice-success' : 'notice-error') +
						' inline';
					out.innerHTML = '<p></p>';
					out.querySelector('p').textContent = msg;
				})
				.catch(function () {
					btn.disabled = false;
					out.className = 'iwiehc-test-email-result notice notice-error inline';
					out.innerHTML = '<p>Request failed.</p>';
				});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var runBtn = document.querySelector('[data-iwiehc-run-checks]');
		if (runBtn) {
			runBtn.setAttribute('data-label', runBtn.textContent);
			runBtn.addEventListener('click', runAllChecks);
		}
		var startBtn = document.querySelector('[data-iwt-job-start][data-iwiehc-disk-finish]');
		var resumeBtn = document.querySelector('[data-iwt-job-resume][data-iwiehc-disk-finish]');
		if (startBtn) {
			startBtn.addEventListener('click', function () {
				watchDiskJob(startBtn.getAttribute('data-progress') || 'iwiehc-disk');
			});
		}
		if (resumeBtn) {
			resumeBtn.addEventListener('click', function () {
				watchDiskJob(resumeBtn.getAttribute('data-progress') || 'iwiehc-disk');
			});
		}
		testEmail();
	});
})();

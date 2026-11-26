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

	function badgeHtml(status, strings) {
		var label = strings[status] || status;
		return (
			'<span class="iwt-badge iwt-badge--' +
			status +
			'"><span class="screen-reader-text">' +
			label +
			': </span>' +
			label +
			'</span>'
		);
	}

	function cardHtml(result, strings) {
		var html =
			'<article class="iwt-result iwt-result--' +
			(result.status || 'info') +
			'" data-status="' +
			(result.status || 'info') +
			'" data-check-id="' +
			escapeHtml(result.id || '') +
			'">';
		html += '<header class="iwt-result__header">';
		html += '<h3 class="iwt-result__title">' + escapeHtml(result.label || '') + '</h3>';
		html += badgeHtml(result.status || 'info', strings);
		html += '</header><div class="iwt-result__body"><p>' + escapeHtml(result.description || '') + '</p>';
		if (result.why) {
			html +=
				'<details class="iwt-result__details"><summary>' +
				escapeHtml(strings.why || 'Why it matters') +
				'</summary><p>' +
				escapeHtml(result.why) +
				'</p></details>';
		}
		if (result.fix) {
			html +=
				'<details class="iwt-result__details"><summary>' +
				escapeHtml(strings.fix || 'How to fix') +
				'</summary><p>' +
				escapeHtml(result.fix) +
				'</p></details>';
		}
		html += '</div></article>';
		return html;
	}

	function updateSummary(container, strings) {
		var cards = container.querySelectorAll('.iwt-result');
		var good = 0;
		var recommended = 0;
		var critical = 0;
		cards.forEach(function (card) {
			var status = card.getAttribute('data-status');
			if (status === 'good') {
				good += 1;
			} else if (status === 'recommended') {
				recommended += 1;
			} else if (status === 'critical') {
				critical += 1;
			}
		});
		var total = good + recommended + critical;
		var score = total === 0 ? 100 : Math.round((good / total) * 100);
		var scoreClass = 'iwt-summary__score--good';
		if (critical > 0) {
			scoreClass = 'iwt-summary__score--critical';
		} else if (recommended > 0) {
			scoreClass = 'iwt-summary__score--recommended';
		}
		var summary = document.querySelector('[data-iwt-summary]');
		if (!summary) {
			return;
		}
		summary.innerHTML =
			'<div class="iwt-summary__score ' +
			scoreClass +
			'" aria-hidden="true"><span>' +
			score +
			'</span></div><ul class="iwt-summary__counts">' +
			'<li><span class="iwt-summary__count">' +
			good +
			'</span> ' +
			escapeHtml(strings.good || 'Good') +
			'</li>' +
			'<li><span class="iwt-summary__count">' +
			recommended +
			'</span> ' +
			escapeHtml(strings.recommended || 'Recommended') +
			'</li>' +
			'<li><span class="iwt-summary__count">' +
			critical +
			'</span> ' +
			escapeHtml(strings.critical || 'Critical') +
			'</li></ul>';
	}

	function runAllChecks() {
		var cfg = window.iwtCore;
		if (!cfg || !cfg.checks || !cfg.checks.length) {
			return;
		}
		var strings = cfg.strings || {};
		var list = document.querySelector('[data-iwt-results]');
		var button = document.querySelector('[data-iwiehc-run-checks]');
		if (!list) {
			return;
		}
		if (button) {
			button.disabled = true;
			button.textContent = strings.running || 'Running…';
		}
		list.innerHTML = '';
		var collected = {};
		var chain = Promise.resolve();
		cfg.checks.forEach(function (check) {
			chain = chain.then(function () {
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
					collected[check.id] = result;
					list.insertAdjacentHTML('beforeend', cardHtml(result, strings));
					updateSummary(list, strings);
				});
			});
		});
		chain.finally(function () {
			if (button) {
				button.disabled = false;
				button.textContent = button.getAttribute('data-label') || 'Run all checks';
			}
			post('iwiehc_cache_results', {
				nonce: cfg.checksNonce,
				results: collected
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

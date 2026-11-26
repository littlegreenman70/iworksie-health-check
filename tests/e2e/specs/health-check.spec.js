// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const {
	createAuthCookies,
	wp,
	screenshotHideCss,
	screenshotsDir,
	wporgScreenshotsDir,
	BASE_URL,
	WP_PATH,
} = require('../helpers/wp-auth');

const HC_URL = '/wp-admin/admin.php?page=iworksie-health-check';
const OVERVIEW_URL = '/wp-admin/admin.php?page=iworks-tools';
const SITE_HEALTH_URL = '/wp-admin/site-health.php';

const FIXTURE = path.join(WP_PATH, 'wp-content/uploads/iwiehc-e2e-fixture');
const MU_DIR = path.join(WP_PATH, 'wp-content/mu-plugins');
const BUDGET_MU = path.join(MU_DIR, 'iwiehc-e2e-budget.php');
const NOTICE_MU = path.join(MU_DIR, 'iwiehc-e2e-notice.php');

const EXAMPLE_PLUGINS = [
	'iworksie-example-one/iworksie-example-one.php',
	'iworksie-example-two/iworksie-example-two.php',
];

async function injectScreenshotCss(page) {
	await page.addStyleTag({ content: screenshotHideCss });
}

async function saveShot(page, name) {
	fs.mkdirSync(screenshotsDir, { recursive: true });
	fs.mkdirSync(wporgScreenshotsDir, { recursive: true });
	const file = `${name}.png`;
	const buf = await page.screenshot({ fullPage: false });
	fs.writeFileSync(path.join(screenshotsDir, file), buf);
	fs.writeFileSync(path.join(wporgScreenshotsDir, file), buf);
}

function rmQuiet(filePath) {
	try {
		fs.unlinkSync(filePath);
	} catch (e) {
		/* ignore */
	}
}

function rmDirRecursive(dir) {
	try {
		fs.rmSync(dir, { recursive: true, force: true });
	} catch (e) {
		/* ignore */
	}
}

function ensureFixture() {
	fs.mkdirSync(path.join(FIXTURE, 'backup-old'), { recursive: true });
	fs.writeFileSync(path.join(FIXTURE, 'backup-old', 'dump.sql'), '-- fixture\n');
	// Enough files that max_per_step=1 cannot finish before Cancel is clicked.
	for (let i = 0; i < 80; i += 1) {
		fs.writeFileSync(path.join(FIXTURE, `file-${i}.txt`), `fixture-${i}\n`);
	}
}

function writeBudgetMu() {
	fs.mkdirSync(MU_DIR, { recursive: true });
	fs.writeFileSync(
		BUDGET_MU,
		`<?php
add_filter( 'iworks_tools_size_scanner_max_per_step', static function () { return 1; } );
add_filter( 'iworks_tools_job_time_budget', static function () { return 0.05; } );
`
	);
}

function writeNoticeMu() {
	fs.mkdirSync(MU_DIR, { recursive: true });
	fs.writeFileSync(
		NOTICE_MU,
		`<?php
add_action( 'admin_notices', static function () {
	echo '<div class="notice notice-warning is-dismissible iwiehc-e2e-dummy-notice"><p>iwiehc e2e dummy notice</p></div>';
} );
`
	);
}

function clearDiskOptions() {
	try {
		wp(['option', 'delete', 'iwiehc_job_size_scanner']);
	} catch (e) {
		/* missing is fine */
	}
	try {
		wp(['option', 'delete', 'iwiehc_disk_scan']);
	} catch (e) {
		/* missing is fine */
	}
}

async function patchJobStartRoot(page, root) {
	await page.evaluate((scanRoot) => {
		const orig = window.fetch;
		window.fetch = function (url, opts) {
			if (opts && opts.body instanceof window.FormData) {
				const action = opts.body.get('action') || '';
				if (String(action).indexOf('job_start') !== -1) {
					opts.body.set('params', JSON.stringify({ root: scanRoot }));
				}
			}
			return orig.apply(this, arguments);
		};
	}, root);
}

test.describe('iWorks.ie Health Check e2e', () => {
	test.beforeEach(async ({ context }) => {
		const cookies = createAuthCookies('localadmin');
		await context.addCookies(cookies);
		await context.grantPermissions(['clipboard-read', 'clipboard-write']);
	});

	test('assets, notices, disk cancel/resume, real scan screenshots', async ({ page }) => {
		const consoleErrors = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				const text = msg.text();
				if (/iwt-core|iwiehc|admin\.js/.test(text)) {
					consoleErrors.push(text);
				}
			}
		});

		const deactivated = [];
		writeNoticeMu();

		try {
			wp(['option', 'delete', 'iwiehc_results']);
			clearDiskOptions();

			// --- Notice placement on Overview + Health Check ---
			await page.goto(OVERVIEW_URL);
			const overviewNotice = page.locator('.iwiehc-e2e-dummy-notice');
			await expect(overviewNotice).toBeVisible();
			await expect(
				page.locator('.iwt-page__header-band .iwiehc-e2e-dummy-notice')
			).toHaveCount(0);
			await expect(page.locator('.iwt-page__title-row .iwt-pill')).toBeVisible();
			const titleRow = page.locator('.iwt-page__title-row');
			await expect(titleRow.locator('h1')).toBeVisible();
			await expect(titleRow.locator('.iwt-pill')).toBeVisible();

			await page.goto(HC_URL + '&tab=overview');
			await expect(page.locator('.iwiehc-e2e-dummy-notice')).toBeVisible();
			await expect(
				page.locator('.iwt-page__header-band .iwiehc-e2e-dummy-notice')
			).toHaveCount(0);
			await expect(page.locator('.iwt-page__title-row .iwt-pill')).toBeVisible();

			const css = page.locator('link[href*="iwt-core.css"]');
			const jsCore = page.locator('script[src*="iwt-core.js"]');
			const jsAdmin = page.locator(
				'script[src*="iworksie-health-check"][src*="admin.js"]'
			);
			await expect(css.first()).toBeAttached();
			await expect(jsCore.first()).toBeAttached();
			await expect(jsAdmin.first()).toBeAttached();

			for (const loc of [css, jsCore, jsAdmin]) {
				const href =
					(await loc.first().getAttribute('href')) ||
					(await loc.first().getAttribute('src'));
				const abs = href.startsWith('http')
					? href
					: new URL(href, BASE_URL).toString();
				const res = await page.request.get(abs);
				expect(res.status(), abs).toBe(200);
			}

			await expect(page.locator('[data-iwt-empty]')).toBeVisible();

			await page.locator('[data-iwiehc-run-checks]').click();
			await expect(page.locator('[data-iwt-results] .iwt-result').first()).toBeVisible({
				timeout: 120000,
			});
			await expect(page.locator('[data-iwt-results] .iwt-result')).toHaveCount(19, {
				timeout: 180000,
			});
			await expect(
				page.locator('[data-iwt-summary] .iwt-summary__score-value')
			).toBeVisible();

			const scoreText = await page
				.locator('[data-iwt-summary] .iwt-summary__score-value')
				.innerText();
			const score = Number(scoreText);
			expect(score).toBeGreaterThanOrEqual(0);
			const scoreEl = page.locator('[data-iwt-summary] .iwt-summary__score');
			if (score >= 90) {
				await expect(scoreEl).toHaveClass(/iwt-summary__score--good/);
			} else if (score >= 60) {
				await expect(scoreEl).toHaveClass(/iwt-summary__score--recommended/);
			} else {
				await expect(scoreEl).toHaveClass(/iwt-summary__score--critical/);
			}

			// --- Disk cancel / resume against tiny fixture ---
			clearDiskOptions();
			ensureFixture();
			writeBudgetMu();

			try {
				await page.goto(HC_URL + '&tab=disk');
				await patchJobStartRoot(page, FIXTURE);
				await page.locator('[data-iwt-job-start]').click();
				await expect
					.poll(
						async () => {
							const now = await page
								.locator(
									'[data-iwt-progress="iwiehc-disk"] [role="progressbar"]'
								)
								.getAttribute('aria-valuenow');
							return Number(now || '0');
						},
						{ timeout: 120000 }
					)
					.toBeGreaterThan(0);
				await page.locator('[data-iwt-job-cancel]').click();
				await expect(page.locator('.iwt-progress__message')).toContainText(/cancel/i, {
					timeout: 60000,
				});

				await page.reload();
				await patchJobStartRoot(page, FIXTURE);
				await expect(page.locator('[data-iwt-job-resume]')).toBeVisible();
				await page.locator('[data-iwt-job-resume]').click();
				await expect(
					page.locator('#iwiehc-disk-results .iwt-stat-tile').first()
				).toBeVisible({ timeout: 180000 });
				await expect(page.locator('#iwiehc-disk-results')).toContainText(
					/Dead weight|Top folders|Total size/i
				);
				await expect(page.locator('[data-iwt-job-start]')).toContainText(/Scan again/i);
				await expect(page.locator('[data-iwt-job-cancel]')).toHaveAttribute('hidden', '');
				await expect(page.locator('[data-iwt-job-resume]')).toHaveAttribute('hidden', '');
			} finally {
				rmQuiet(BUDGET_MU);
				rmDirRecursive(FIXTURE);
				clearDiskOptions();
			}

			// --- Real full scan + screenshots (examples deactivated) ---
			for (const plugin of EXAMPLE_PLUGINS) {
				try {
					wp(['plugin', 'deactivate', plugin]);
					deactivated.push(plugin);
				} catch (e) {
					/* already inactive */
				}
			}

			await page.goto(HC_URL + '&tab=disk');
			await page.locator('[data-iwt-job-start]').click();
			await expect(page.locator('#iwiehc-disk-results .iwt-stat-tile').first()).toBeVisible({
				timeout: 600000,
			});
			const diskText = await page.locator('#iwiehc-disk-results').innerText();
			expect(diskText).not.toMatch(/\/opt\/homebrew\/var\/www/);
			expect(diskText).toMatch(/wp-content|Total size/i);
			// Real site is far larger than the 40 B fixture.
			expect(diskText).not.toMatch(/Total size\s*40\s*B/i);

			await page.goto(HC_URL + '&tab=overview');
			await page.locator('[data-iwiehc-run-checks]').click();
			await expect(page.locator('[data-iwt-results] .iwt-result').first()).toBeVisible({
				timeout: 180000,
			});
			await expect(page.locator('[data-iwt-results] .iwt-result')).toHaveCount(19, {
				timeout: 180000,
			});

			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-1');

			await page.goto(HC_URL + '&tab=disk');
			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-2');

			await page.goto(HC_URL + '&tab=report');
			const report = page.locator('#iwiehc-report-source');
			await expect(report).toBeVisible();
			const reportText = await report.innerText();
			expect(reportText).not.toMatch(/DB_PASSWORD/);
			expect(reportText).not.toMatch(/AUTH_KEY/);
			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-3');

			await page.locator('[data-iwt-copy="iwiehc-report-source"]').click();
			const clipboard = await page.evaluate(() => navigator.clipboard.readText());
			expect(clipboard.length).toBeGreaterThan(20);

			const [download] = await Promise.all([
				page.waitForEvent('download'),
				page
					.locator('a[href*="iwiehc"], a.button', { hasText: /download/i })
					.first()
					.click(),
			]);
			const downloadPath = await download.path();
			expect(downloadPath).toBeTruthy();
			const dlBody = fs.readFileSync(downloadPath, 'utf8');
			expect(dlBody).not.toMatch(/DB_PASSWORD/);
			expect(dlBody).not.toMatch(/AUTH_KEY/);

			await page.goto(HC_URL + '&tab=tools');
			await page.locator('#iwiehc-send-test-email').click();
			await expect(page.locator('#iwiehc-test-email-result')).not.toHaveText(/Sending/i, {
				timeout: 30000,
			});
			const emailMsg = await page.locator('#iwiehc-test-email-result').innerText();
			expect(emailMsg).not.toMatch(/\.\./);
			expect(emailMsg.length).toBeGreaterThan(5);
			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-4');

			await page.goto(SITE_HEALTH_URL);
			await expect(page.locator('body')).toContainText(/iWorks\.ie Health Check/i);
			const badge = page.locator('.health-check-accordion .badge', {
				hasText: 'iWorks.ie Health Check',
			});
			await expect(badge.first()).toBeVisible({ timeout: 60000 });
			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-5');

			await page.goto(OVERVIEW_URL);
			await expect(page.locator('.iwt-card', { hasText: 'Health Check' })).toBeVisible();
			await expect(page.locator('#adminmenu a', { hasText: 'Example' })).toHaveCount(0);

			expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
		} finally {
			rmQuiet(NOTICE_MU);
			rmQuiet(BUDGET_MU);
			rmDirRecursive(FIXTURE);
			clearDiskOptions();
			for (const plugin of deactivated) {
				try {
					wp(['plugin', 'activate', plugin]);
				} catch (e) {
					/* ignore */
				}
			}
		}
	});
});

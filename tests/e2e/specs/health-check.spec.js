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
} = require('../helpers/wp-auth');

const HC_URL = '/wp-admin/admin.php?page=iworksie-health-check';
const OVERVIEW_URL = '/wp-admin/admin.php?page=iworks-tools';
const SITE_HEALTH_URL = '/wp-admin/site-health.php';

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

test.describe('iWorks.ie Health Check e2e', () => {
	test.beforeEach(async ({ context }) => {
		const cookies = createAuthCookies('localadmin');
		await context.addCookies(cookies);
		await context.grantPermissions(['clipboard-read', 'clipboard-write']);
	});

	test('assets load, empty run, disk, report, tools, site health, overview', async ({
		page,
	}) => {
		const consoleErrors = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				const text = msg.text();
				if (/iwt-core|iwiehc|admin\.js/.test(text)) {
					consoleErrors.push(text);
				}
			}
		});

		// 1 + 2: empty state then run checks
		wp(['option', 'delete', 'iwiehc_results']);
		await page.goto(HC_URL + '&tab=overview');

		const css = page.locator('link[href*="iwt-core.css"]');
		const jsCore = page.locator('script[src*="iwt-core.js"]');
		const jsAdmin = page.locator('script[src*="iworksie-health-check"][src*="admin.js"]');
		await expect(css.first()).toBeAttached();
		await expect(jsCore.first()).toBeAttached();
		await expect(jsAdmin.first()).toBeAttached();

		for (const loc of [css, jsCore, jsAdmin]) {
			const href =
				(await loc.first().getAttribute('href')) ||
				(await loc.first().getAttribute('src'));
			const abs = href.startsWith('http') ? href : new URL(href, BASE_URL).toString();
			const res = await page.request.get(abs);
			expect(res.status(), abs).toBe(200);
		}

		await expect(page.locator('[data-iwt-empty]')).toBeVisible();
		await expect(page.locator('.iwt-summary__score')).toHaveCount(0);

		await page.locator('[data-iwiehc-run-checks]').click();
		await expect(page.locator('[data-iwt-results] .iwt-result').first()).toBeVisible({
			timeout: 120000,
		});
		await expect(page.locator('[data-iwt-results] .iwt-result')).toHaveCount(19, {
			timeout: 180000,
		});
		await expect(page.locator('[data-iwt-summary] .iwt-summary__score-value')).toBeVisible();

		const scoreText = await page
			.locator('[data-iwt-summary] .iwt-summary__score-value')
			.innerText();
		expect(Number(scoreText)).toBeGreaterThanOrEqual(0);

		const critical = Number(
			await page.locator('[data-iwt-filter="critical"] .iwt-summary__count').innerText()
		);
		const recommended = Number(
			await page.locator('[data-iwt-filter="recommended"] .iwt-summary__count').innerText()
		);
		const good = Number(
			await page.locator('[data-iwt-filter="good"] .iwt-summary__count').innerText()
		);
		const cards = await page.locator('[data-iwt-results] .iwt-result').count();
		expect(critical + recommended + good).toBeLessThanOrEqual(cards);

		await injectScreenshotCss(page);
		await saveShot(page, 'screenshot-1');

		// 3: Disk tab — start, cancel >0%, reload, resume, finish.
		// Cap the per-step budget so a full ABSPATH scan cannot finish in one AJAX hop.
		wp(['option', 'delete', 'iwiehc_job_size_scanner']);
		wp(['option', 'delete', 'iwiehc_disk_scan']);

		const fixture =
			'/opt/homebrew/var/www/www.iworks.new/wp-content/uploads/iwiehc-e2e-fixture';
		const muDir = '/opt/homebrew/var/www/www.iworks.new/wp-content/mu-plugins';
		const muFile = muDir + '/iwiehc-e2e-budget.php';
		fs.mkdirSync(muDir, { recursive: true });
		fs.writeFileSync(
			muFile,
			`<?php
add_filter( 'iworks_tools_size_scanner_max_per_step', static function () { return 1; } );
`
		);

		async function patchJobStartRoot(root) {
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

		try {
			await page.goto(HC_URL + '&tab=disk');
			await patchJobStartRoot(fixture);
			await page.locator('[data-iwt-job-start]').click();
			await expect
				.poll(
					async () => {
						const now = await page
							.locator('[data-iwt-progress="iwiehc-disk"] [role="progressbar"]')
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
			await patchJobStartRoot(fixture);
			await page.locator('[data-iwt-job-resume]').click();
			await expect(page.locator('#iwiehc-disk-results .iwt-stat-tile').first()).toBeVisible({
				timeout: 180000,
			});
			await expect(page.locator('#iwiehc-disk-results')).toContainText(
				/Dead weight|Top folders|Total size/i
			);
			await injectScreenshotCss(page);
			await saveShot(page, 'screenshot-2');
		} finally {
			try {
				fs.unlinkSync(muFile);
			} catch (e) {
				/* ignore */
			}
		}

		// 4: Report tab
		await page.goto(HC_URL + '&tab=report');
		const report = page.locator('#iwiehc-report-source');
		await expect(report).toBeVisible();
		const reportText = await report.innerText();
		expect(reportText).not.toMatch(/DB_PASSWORD/);
		expect(reportText).not.toMatch(/AUTH_KEY/);

		await page.locator('[data-iwt-copy="iwiehc-report-source"]').click();
		const clipboard = await page.evaluate(() => navigator.clipboard.readText());
		expect(clipboard.length).toBeGreaterThan(20);

		const [download] = await Promise.all([
			page.waitForEvent('download'),
			page.locator('a[href*="iwiehc"], a.button', { hasText: /download/i }).first().click(),
		]);
		const downloadPath = await download.path();
		expect(downloadPath).toBeTruthy();
		const dlName = download.suggestedFilename();
		expect(dlName).toMatch(/\.md$/i);
		const dlBody = fs.readFileSync(downloadPath, 'utf8');
		expect(dlBody).not.toMatch(/DB_PASSWORD/);
		expect(dlBody).not.toMatch(/AUTH_KEY/);
		const adminEmail = wp(['option', 'get', 'admin_email']);
		if (adminEmail) {
			expect(dlBody).not.toContain(adminEmail);
		}
		await injectScreenshotCss(page);
		await saveShot(page, 'screenshot-3');

		// 5: Tools tab — test email (expect blocked/failed locally)
		await page.goto(HC_URL + '&tab=tools');
		await page.locator('#iwiehc-send-test-email').click();
		await expect(page.locator('#iwiehc-test-email-result')).not.toHaveText(/Sending/i, {
			timeout: 30000,
		});
		await expect(page.locator('#iwiehc-test-email-result')).toContainText(/.{10,}/, {
			timeout: 30000,
		});
		const emailMsg = await page.locator('#iwiehc-test-email-result').innerText();
		expect(emailMsg.length).toBeGreaterThan(5);
		await injectScreenshotCss(page);
		await saveShot(page, 'screenshot-4');

		// 6: Site Health
		await page.goto(SITE_HEALTH_URL);
		await expect(page.locator('body')).toContainText(/iWorks\.ie Health Check|PHP version|Autoload|Cron|dead weight|Dead weight/i);
		await injectScreenshotCss(page);
		await saveShot(page, 'screenshot-5');

		// 7: Overview card
		await page.goto(OVERVIEW_URL);
		await expect(page.locator('.iwt-card', { hasText: 'Health Check' })).toBeVisible();

		expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
	});
});

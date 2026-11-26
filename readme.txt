=== iWorks.ie Health Check ===
Contributors: iworksie
Tags: health check, site health, diagnostics, disk space, performance
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only site health checks with plain-English fixes, disk/dead-weight finder and a shareable report.

== Description ==

iWorks.ie Health Check is the safe, read-only alternative to heavy troubleshooting plugins.

**Read-only: this plugin never changes your site.** There is no troubleshooting mode, no file deletion, and no settings that alter behaviour.

It checks your server, WordPress, database and disk space, then explains each finding in plain English with:

* Why it matters
* How to fix it

Status language matches WordPress Site Health: Good, Recommended, and Critical.

Extra capabilities beyond core Site Health:

* Disk usage and dead-weight finder (old backups, caches, `.git`, `node_modules`, logs, archives)
* Database and autoload sizes
* Cron health
* PHP limits explained
* Copy / download a Markdown report for your developer

Privacy: this plugin makes no external requests and collects no data. It only reads local WordPress, PHP and filesystem information.

Need a custom plugin? iWorks builds them: https://www.iworks.ie

== Installation ==

1. Upload the `iworksie-health-check` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New.
2. Activate the plugin through the Plugins screen.
3. Open **iWorks Tools → Health Check**.

== Frequently Asked Questions ==

= Can it break my site? =

No. It is strictly read-only. It never switches themes, disables plugins, deletes files, or changes settings.

= Does it delete files or database rows? =

No. The disk scan only lists candidates. Cleaning is always left to you and your developer after a backup.

= How is this different from WordPress Site Health? =

It adds disk/dead-weight scanning, autoload and table size detail, cron overdue detection, plain-English fixes on every finding, and a shareable report, while staying read-only.

= Does it send data anywhere? =

No. It makes no external HTTP requests and collects no analytics.

== Screenshots ==

1. Overview with score summary and grouped results.
2. Disk & dead weight scan with progress and findings.
3. Shareable Markdown report (copy / download).
4. Tools tab with test email.
5. Checks also appear under Tools → Site Health.

== Changelog ==

= 1.0.0 =
* Initial release: server, WordPress, database and disk checks, report, test email, Site Health bridge, WP-CLI.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

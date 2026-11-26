# Decisions

| Date (Europe/Dublin) | Decision | Reason |
|----------------------|----------|--------|
| 2026-11-26 | Place repo at `/opt/homebrew/var/www/iworksie-health-check` | Sibling of core and the local WP test bed. |
| 2026-11-26 | wordpress.org contributor slug `iworksie` is a placeholder | Must match the real account before submission. |
| 2026-11-26 | Final `.wordpress-org` artwork is placeholder solid-colour wordmark images | Final design pending; Plugin Check and listing layout can proceed. |
| 2026-11-26 | CI used `CORE_REPO_TOKEN` while core was private | Superseded 2026-11-26: core is public; token/COMPOSER_AUTH removed from CI. |
| 2026-11-26 | Health Check 1.0.1 only adapts DiskSummary to core 1.0.1 result shape | No Health Check feature changes beyond consuming leaner scan results. |
| 2026-11-26 | CI strips path repo via PHP then `composer update` | `composer config --unset repositories.0` left the path entry; lock is gitignored so update is required. |
| 2026-11-26 | PHP EOL table hard-coded with yearly update comment | Avoid remote HTTP; Site Health style local table. |
| 2026-11-26 | Autoload owner guess uses active/inactive plugin slug prefixes with "possibly" wording | Heuristic only; never claim certainty. |
| 2026-11-26 | Leftover DB tables are Info only | Heuristic prefix matching is too uncertain for Recommended/Critical. |
| 2026-11-26 | Test email reports `wp_mail` / `wp_mail_failed` plainly; local mu-plugin blocks mail | Matches local-dev-safety; explain "sent" vs delivered and SMTP. |
| 2026-11-26 | Report redacts secrets; admin email shows domain only | Privacy requirement from the product brief. |
| 2026-11-26 | Disk scan uses core `SizeScanner` job id `size_scanner` | Do not reimplement scanning. |
| 2026-11-26 | Cache results in non-autoloaded option `iwiehc_results` | Prompt requirement; uninstall deletes it. |

## Candidates for core

- None required for 1.0.0. Threshold helpers and option-owner guessing stay in the plugin.

## Before wordpress.org submission

- Confirm contributor account slug matches `iworksie` or update `readme.txt`.
- Replace placeholder banner/icon artwork.
- Confirm Stable tag / SVN trunk after plugin approval.

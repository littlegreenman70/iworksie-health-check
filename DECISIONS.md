# Decisions

| Date (Europe/Dublin) | Decision | Reason |
|----------------------|----------|--------|
| 2026-11-26 | Place repo at `/opt/homebrew/var/www/iworksie-health-check` | Sibling of core and the local WP test bed. |
| 2026-11-26 | wordpress.org contributor slug `iworksie` is a placeholder | Must match the real account before submission. |
| 2026-11-26 | Final `.wordpress-org` artwork is placeholder solid-colour wordmark images | Final design pending; Plugin Check and listing layout can proceed. |
| 2026-11-26 | CI uses `COMPOSER_AUTH` from repository secret `CORE_REPO_TOKEN` for private core | Core is private; without the secret CI fails with a clear message. User may make core public instead. |
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
- Ensure `CORE_REPO_TOKEN` is set for CI, or make `iworks-tools-core` public.
- Confirm Stable tag / SVN trunk after plugin approval.

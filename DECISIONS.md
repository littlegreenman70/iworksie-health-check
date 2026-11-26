# Decisions

| Date (Europe/Dublin) | Decision | Reason |
|----------------------|----------|--------|
| 2026-11-26 | Place repo at `/opt/homebrew/var/www/iworksie-health-check` | Sibling of core and the local WP test bed. |
| 2026-11-26 | wordpress.org contributor is `littlegreenman` | Plugin slug / text domain / prefix stay `iworksie-health-check`; the w.org account is separate. |
| 2026-11-26 | Final `.wordpress-org` banner/icon artwork is placeholder solid-colour wordmark images | Final design pending; Plugin Check and listing layout can proceed. |
| 2026-11-26 | Screenshots 1–5 are real Playwright captures of www.iworks.new admin (1280×800) | Walkthrough showed placeholders; 1.1.0 requires browser proof. |
| 2026-11-26 | Playwright auth uses WP-CLI `wp_generate_auth_cookie` (secure_auth + logged_in), no password typing | Local HTTPS; ignoreHTTPSErrors; short-lived cookies. |
| 2026-11-26 | E2E disk cancel/resume uses fixture under uploads + `iworks_tools_size_scanner_max_per_step=1` mu-plugin | Full ABSPATH finishes in one AJAX hop on this machine; multi-step required to exercise cancel. |
| 2026-11-26 | Disk toolbar modes: idle / running / resume / finished; only show Cancel while running and Resume after cancel/interrupt | Finished scans were showing Scan + Resume + Cancel with a 0% bar. |
| 2026-11-26 | Display paths relative to ABSPATH; keep absolute in `title` | Full server paths leaked into Top folders / Dead weight / Report. |
| 2026-11-26 | Breakdown bar is site-total based with an Outside wp-content segment; Uploads green / Plugins blue / Themes purple / Other grey / Outside amber | Uploads and Plugins were nearly the same blue. |
| 2026-11-26 | `web_root_risks` store `{path,bytes,is_dir}`; backup folders included; check lists up to 10 matches and falls back to shallow scan when disk scan is older than 7 days | Critical card said “1 backup…” with no names, and could reuse stale fixture data. |
| 2026-11-26 | E2E cleans fixture + job/disk options in `finally`; screenshots run a real uncapped site scan with example plugins deactivated | Screenshots previously showed 40 B fixture Criticals. |
| 2026-11-26 | Strip trailing `.` from test-email error detail before joining the failure sentence | Produced a double full stop (“error.. On local…”). |
| 2026-11-26 | Health Check 1.0.1 only adapts DiskSummary to core 1.0.1 result shape | No Health Check feature changes beyond consuming leaner scan results. |
| 2026-11-26 | CI strips path repo via PHP then `composer update` | `composer config --unset repositories.0` left the path entry; lock is gitignored so update is required. |
| 2026-11-26 | Quality CI matrix is PHP 8.3 only; add PHP 7.4 `php -l` on the built zip | Strauss 0.32 cannot run on 7.4. Runtime still declares PHP 7.4+; syntax lint keeps that promise honest. |
| 2026-11-26 | PHP EOL table hard-coded with yearly update comment | Avoid remote HTTP; Site Health style local table. |
| 2026-11-26 | Autoload owner guess uses active/inactive plugin slug prefixes with "possibly" wording | Heuristic only; never claim certainty. |
| 2026-11-26 | Leftover DB tables are Info only | Heuristic prefix matching is too uncertain for Recommended/Critical. |
| 2026-11-26 | Test email reports `wp_mail` / `wp_mail_failed` plainly; local mu-plugin blocks mail | Matches local-dev-safety; explain "sent" vs delivered and SMTP. |
| 2026-11-26 | Report redacts secrets; admin email shows domain only | Privacy requirement from the product brief. |
| 2026-11-26 | Disk scan uses core `SizeScanner` job id `size_scanner` | Do not reimplement scanning. |
| 2026-11-26 | Cache results in non-autoloaded option `iwiehc_results` | Prompt requirement; uninstall deletes it. |
| 2026-11-26 | Intercept `iwiehc_run_check` at priority 5 to store each Result server-side | Core has no after-check hook; wrapper dies before core's handler. Finished JS call only stamps time. |
| 2026-11-26 | `save_disk_summary` reads only `$state['result']` | Avoid depending on working keys Manager may change. |
| 2026-11-26 | Action URLs in cached results must be `admin_url()` links | Limit XSS/open-redirect surface if a check ever adds a URL. |
| 2026-11-26 | Require tools-core `^1.1` | Symlink asset fix and shared UI chrome ship in 1.1.0. |
| 2026-11-26 | Backup web-root: ignore `wp-content/upgrade` and `upgrade-temp-backup` (filterable); empty folders never count; Critical only for dump/archive extensions (incl. `.wpress`/`.bak`); other backup-named files are Recommended with label "Possible backup files in the web root". | WP core's rollback folder and content exports were Critical false positives on www.iworks.new. |

## Candidates for core

- Optional `iworks_tools_check_ran` action after each AJAX check would remove the priority-5 intercept.

## Before wordpress.org submission

- Contributor in `readme.txt` is `littlegreenman` (plugin slug remains `iworksie-health-check`).
- Replace placeholder banner/icon artwork.
- Confirm Stable tag / SVN trunk after plugin approval.
- Core is public (`littlegreenman70/iworks-tools-core`); no private-token CI setup.

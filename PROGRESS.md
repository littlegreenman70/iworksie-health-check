# Progress — iWorks.ie Health Check

| Step | Status | Notes |
|------|--------|-------|
| 1 Repo setup | done | Composer, Strauss, symlink into www.iworks.new |
| 2 Plugin bootstrap | done | Main file, Plugin, uninstall, init boot |
| 3 Checks | done | 19 checks across server/WP/DB/disk |
| 4 Screens | done | Overview, Disk, Report, Tools + Site Health + WP-CLI |
| 5 readme.txt | done | wordpress.org format |
| 6 Tests | done | Unit 11 OK; Integration 5 OK; Plugin Check via CI |
| 7 Build and CI | done | Public core; PHP 8.3 quality + PHP 7.4 `php -l` on build |
| 8 GitHub | done | tag v1.0.0 |
| 9 Core 1.0.1 | done | DiskSummary lean scan; bump 1.0.1 |
| 10 Review 1.0.2 | done | DiskSummary tests, server-side results, PHP 7.4 lint job |

## v1.0.2 notes (2026-11-26 Europe/Dublin)

- Unit tests for DiskSummary against core 1.0.1 result fixture (incl. overflow).
- `save_disk_summary` uses only `$state['result']`.
- Each check result stored server-side via priority-5 `iwiehc_run_check`; finished JS call only stamps time.
- Action URLs limited to `admin_url()` when caching.
- PHP 7.4 syntax lint on built plugin artifact.
- Tag `v1.0.2`.

# Progress — iWorks.ie Health Check

| Step | Status | Notes |
|------|--------|-------|
| 1 Repo setup | done | Composer, Strauss, symlink into www.iworks.new |
| 2 Plugin bootstrap | done | Main file, Plugin, uninstall, init boot |
| 3 Checks | done | 19 checks across server/WP/DB/disk |
| 4 Screens | done | Overview, Disk, Report, Tools + Site Health + WP-CLI |
| 5 readme.txt | done | wordpress.org format; contributor `littlegreenman` |
| 6 Tests | done | Unit 11 OK; Integration 5 OK; Plugin Check via CI |
| 7 Build and CI | done | Public core; PHP 8.3 quality + PHP 7.4 `php -l` on build |
| 8 GitHub | done | tag v1.0.0 |
| 9 Core 1.0.1 | done | DiskSummary lean scan; bump 1.0.1 |
| 10 Review 1.0.2 | done | DiskSummary tests, server-side results, PHP 7.4 lint job |
| 11 Release 1.0.3 | done | Contributor `littlegreenman`; tag v1.0.3 |
| 12 Release 1.1.0 | done | Core 1.1.0 assets/design; Playwright e2e; real screenshots |

## v1.1.0 notes (2026-11-26 Europe/Dublin)

- Consumes `iworksie/tools-core` 1.1.0 (symlink-safe `plugins_url` assets + polished UI).
- Screens adapted: empty state, accordion results, disk stat tiles + bar chart, monospace report, tools card.
- Tagline matches readme: "Read-only: never changes your site's settings, content or files."
- Playwright e2e in `tests/e2e/` with WP-CLI auth cookies; real screenshots in `.wordpress-org/` and `~/Downloads/iworks-health-check-screens/`.

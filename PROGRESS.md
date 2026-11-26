# Progress — iWorks.ie Health Check

| Step | Status | Notes |
|------|--------|-------|
| 1 Repo setup | done | Composer, Strauss, symlink into www.iworks.new |
| 2 Plugin bootstrap | done | Main file, Plugin, uninstall, init boot |
| 3 Checks | done | 19 checks across server/WP/DB/disk |
| 4 Screens | done | Overview, Disk, Report, Tools + Site Health + WP-CLI |
| 5 readme.txt | done | wordpress.org format |
| 6 Tests | done | Unit 8 OK; Integration 5 OK; Plugin Check clean |
| 7 Build and CI | done | Public core; path repo stripped in CI; quality on PHP 8.3 |
| 8 GitHub | done | https://github.com/littlegreenman70/iworksie-health-check tag v1.0.0 |
| 9 Core 1.0.1 | done | DiskSummary lean scan; bump 1.0.1; CI green run 45844881367 |

## v1.0.1 notes (2026-11-26 Europe/Dublin)

- `composer update iworksie/tools-core` → 1.0.1
- Removed `CORE_REPO_TOKEN` / `COMPOSER_AUTH` from CI and docs
- CI strips path repository then `composer update` against public VCS
- Tag `v1.0.1`

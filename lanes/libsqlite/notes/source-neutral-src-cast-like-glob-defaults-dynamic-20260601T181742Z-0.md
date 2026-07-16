# Source-Neutral CAST/LIKE/GLOB Defaults Dynamic

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T181742Z-0`
Base accepted HEAD: `46132b002aae86d77139b7f5e361edf24e0035ba`

## Scope

- Neutralized the direct CAST affinity and LIKE/GLOB collation corpus fixtures
  from legacy option-table names to generic `app_settings`, `setting_id`,
  `key_name`, `key_value`, and `load_policy` terms.
- Added `SQLiteSourceNeutralCastLikeGlobDefaultsDynamicTest.php` to guard the
  owned CAST/LIKE/GLOB source defaults and the two direct fixtures against
  legacy domain terms.
- No compatibility aliases, wrapper APIs, lane counters, or mapped upstream
  denominator rows are changed; this is source-neutral cleanup over existing
  behavior coverage.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteCastAffinityComparisonCorpusTest.php && php -l lanes/libsqlite/tests/SQLiteLikeCollationCurrentNext65Test.php && php -l lanes/libsqlite/tests/SQLiteSourceNeutralCastLikeGlobDefaultsDynamicTest.php`
  - Result: all changed PHP test files reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastAffinityComparisonCorpusTest.php lanes/libsqlite/tests/SQLiteLikeCollationCurrentNext65Test.php lanes/libsqlite/tests/SQLiteSourceNeutralCastLikeGlobDefaultsDynamicTest.php`
  - Result: `3 test files, 252 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralCastLikeGlobDefaultsDynamicTest.php`
  - Result: `2 test files, 40 assertions, 0 failures`.
- Targeted source/fixture term scan:
  - `rg -n "wp_options|wp_sitemeta|wp_|blog_id|option_id|option_name|option_value|autoload|WP_LOCALE|siteurl|active_plugins" ...`
  - Result: no matches for the owned CAST/LIKE/GLOB source defaults and direct fixtures.

## Dependency Closure

No new support component is needed. The cleanup reuses the existing native PHP
CAST expression evaluator, SELECT SQL executor, LIKE/GLOB range planners, and
collation helpers with neutral app-settings fixtures.

## Non-Overlap

This slice does not add upstream PASS rows, runner metadata, dashboard/root
edits, compatibility shims, or new domain-shaped wrappers. It is bounded to
the source-neutral CAST/LIKE/GLOB defaults and direct corpus fixtures named by
the current micro-slice.

Root harness: not run - isolated micro-slice.

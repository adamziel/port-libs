# Source-Neutral Compound Window Defaults Dynamic

Slice: `source-neutral-src-compound-window-defaults-dynamic-20260601T072027Z-0`

Cleaned another compound/window source-neutral batch away from legacy option-table defaults:

- Replaced hardcoded recursive CTE references in compound/window trace helpers with SQL-derived CTE names.
- Neutralized row-value UPSERT window defaults from option/autoload-shaped keys to generic setting/load-policy keys.
- Renamed the directly coupled row-value UPSERT test and example away from numbered current-source names.
- Updated directly coupled compound recursive tests/examples to generic application setting fixtures while preserving the same recursive, affinity, window, and LIMIT behavior assertions.
- Expanded the source-neutral guard so this batch's source files are scanned for legacy domain defaults.

Dependency closure: no new support component is needed; this reuses the existing recursive CTE, compound SELECT, window, affinity, row-value UPSERT, and SELECT SQL helpers.

Verification:

- `php -l` passed for all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceTest.php lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowTest.php lanes/libsqlite/tests/SQLiteCompoundRecursiveUnionSourceBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectRecursiveAffinityLimitTest.php lanes/libsqlite/tests/SQLiteWindowRowValueUpsertSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 7 files, 1035 assertions, 0 failures.
- Changed example self-tests passed for `application-compound-recursive-window-order-current-source.php`, `application-compound-recursive-affinity-window.php`, `application-compound-recursive-union-source-boundary.php`, `application-select-sql-compound-recursive-affinity-limit.php`, and `application-window-rowvalue-upsert-source-neutral-defaults.php`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'` passed.
- Domain-term scan over the touched source/tests/examples returned no matches for the removed legacy table/key/load-policy/default names.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.

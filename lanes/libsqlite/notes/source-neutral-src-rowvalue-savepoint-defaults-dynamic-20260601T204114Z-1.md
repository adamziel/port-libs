# Source Neutral Row-Value Savepoint Defaults Dynamic

- Scope: row-value/savepoint source-neutral cleanup guard on accepted base `aaf9d065b2ec92f6b38bc82c8b7a077d45f36128`.
- Source scan before editing found no remaining `wp_`, `wp_options`, `option_*`, `blog_id`, or `autoload` hits in `lanes/libsqlite/src`.
- Patch broadens the row-value source-neutral guard from a hand-maintained source list to a dynamic `SQLite*RowValue*.php` source inventory, plus `SQLiteRowIdColumn.php` and `SQLiteUpdateDeleteReturningSql.php`.
- Direct no-domain API guard now uses the same dynamic row-value source inventory so newly added row-value/savepoint production helpers cannot reintroduce legacy domain terms outside the static list.
- Dependency closure: no new support component needed; this reuses native row-value UPDATE/DELETE RETURNING, UPSERT/conflict, savepoint, rowid resolution, and source-neutral no-domain guards.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed.
- `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 2 test files, 43 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` passed.

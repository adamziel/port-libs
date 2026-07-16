# real-upstream-corpus-json102-select-sql-tree-search-dynamic-20260531

## Upstream Source

- SQLite upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Ported scenarios:
  - `json102-1130`: `SELECT DISTINCT json_extract(big.json,'$.id') FROM big, json_tree(big.json,'$.partlist') WHERE json_tree.key='uuid' AND json_tree.value='6fa5181e-5721-11e5-a04e-57f3d7b32808'`
  - `json102-1131`: same search with explicit root `json_tree(big.json,'$')`
  - `json102-1132`: same search with default root `json_tree(big.json)`

## Behavior Delta

- Added `SQLiteRealUpstreamJson102SelectSqlTreeSearchDynamic20260531Test.php` with 270 dynamic `app_records` fixtures.
- Each fixture runs the three upstream SELECT DISTINCT search shapes against text JSON and JSONB documents, proves duplicate source rows collapse to one projected id, and runs commuted JSONB predicates so default-root container rows compare without throwing.
- Fixed `SQLiteSelectPredicate` comparison coercion so `SQLiteJsonSubtypeValue` sorts and compares as JSON text. This removes the default-root `json_tree()` blocker where object/array rows could abort equality comparison before scalar uuid rows were reached.

## Evidence

- Red-first probe before the fix: `SELECT DISTINCT json_extract(big.docb,'$.id') ... FROM app_records AS big, json_tree(big.docb,'$') AS jt WHERE jt.key='uuid' AND jt.value='target'` failed with `SQLite SELECT comparison values must be scalar, BLOB, or NULL`.
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102SelectSqlTreeSearchDynamic20260531Test.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102SelectSqlTreeSearchDynamic20260531Test.php`: 1 test files, 6486 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102SelectSqlTreeSearchDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102TreeSearchDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteSelectPredicateLikeGlobAffinityCurrentSourceNext109Test.php lanes/libsqlite/tests/SQLiteSelectPredicateRealAffinityLikeGlobCurrentSourceNext120Test.php`: 4 test files, 26176 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files, 3 assertions, 0 failures.
- `php -r '$json = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid JSON\n";'`: lane-status.json valid JSON.
- `git diff --check -- lanes/libsqlite`: passed.

## Status Delta

- `lane-status.json` `phpPass`: `3847998 -> 3854484` (`+6486` focused assertions).
- Mapped upstream denominator remains `1589 / 1589`.
- Root harness not run: isolated micro-slice.

## Non-Overlap And Dependency Closure

- Non-overlap: this ports upstream `json102-1130..1132` parser-level SELECT/json_tree search behavior, not the already accepted JSON hidden/visible constraints, JSON table cursor/source wiring, JSON aggregate/window behavior, or existing direct `SQLiteJsonTree` scalar parity tests.
- Dependency closure: no new support component is needed; the slice reuses existing JSONB, JSON tree, and SELECT SQL execution support.

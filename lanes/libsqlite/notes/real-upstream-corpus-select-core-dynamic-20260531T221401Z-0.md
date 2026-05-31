# real-upstream-corpus-select-core-dynamic-20260531T221401Z-0

Lane: `libsqlite`
Slice: `real-upstream-corpus-select-core-dynamic-20260531T221401Z-0`
Base accepted HEAD: `6f5231cf32a6827b588751d49dba711af77e658b`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported section: `e_select-1.3.1` through `e_select-1.3.11`
- Upstream behavior: `ON` constraints are evaluated for every row of the cartesian product as boolean expressions. Numeric, text, `NULL`, equality, left-row predicates, and `CASE` expressions determine which joined rows survive.

## Patch

- Added `SQLiteRealUpstreamESelectOnTruthDynamic20260531T221401ZTest.php`.
- The test creates 1000 dynamic generic datasets and rotates the upstream join spellings `,`, `CROSS JOIN`, and `INNER JOIN`.
- Each dynamic case verifies the eleven upstream `e_select-1.3` ON-expression behaviors with flat row output, row count, edge-value, and fingerprint assertions.
- No production source change was needed; current `SQLiteSelectSql` already supports this upstream behavior.

## Non-Overlap

This slice owns only ON-clause truthiness and row-dependent CASE filtering. It avoids accepted equality JOIN syntax coverage, `USING`, `NATURAL` / `LEFT JOIN`, e_select2 dataset join/collation/associativity batches, grouped SELECT text, HAVING min/max, ORDER BY collation/resolution, compound SELECT, JSON-table sources/constraints, B-tree, WAL, VFS, PRAGMA, and metadata-only runner rows.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` join predicates, SQL truthiness conversion, text numeric-prefix conversion, and CASE expression evaluation against row-array inputs.

## Status Delta

- Focused TestRunner PASS growth: `+1002` (`3946112` -> `3947114` in `lanes/libsqlite/lane-status.json`).
- Focused behavior assertions: `46010`.
- Mapped upstream denominator: unchanged at `1589 / 1589`.

## Verification

Completed local focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelectOnTruthDynamic20260531T221401ZTest.php`
  Result: no syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectOnTruthDynamic20260531T221401ZTest.php`
  Result: `1 test files, 46010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  Result: `1 test files, 3 assertions, 0 failures`.
- `php -r '$json = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`
  Result: `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite`
  Result: no output.

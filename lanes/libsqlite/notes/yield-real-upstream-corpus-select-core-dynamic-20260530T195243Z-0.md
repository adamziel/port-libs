# Real upstream SELECT core dynamic corpus

Slice: `real-upstream-corpus-select-core-dynamic-20260530T195243Z-0`

Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`

Ported behavior:

- `select1-1.4` through `select1-1.7`: basic select-list extraction and column order.
- `select1-1.8`: wildcard expansion over a single table.
- `select1-1.8.1` and `select1-1.8.3`: repeated wildcard projections, including literal columns around wildcard output.
- `select1-1.9` through `select1-1.11.1`: comma join source order, literal tails, qualified columns, and reversed source wildcard order.

Implementation fix:

- `SQLiteSelectProjection::project()` now preserves duplicate SELECT output names by suffixing later associative-array keys as `name#2`, `name#3`, and so on. This keeps PHP row arrays representable while matching SQLite's behavior of returning repeated output columns instead of rejecting the query.

Focused test growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelect1RepeatedWildcardDynamicTest.php`.
- Selected PASS-line delta: `+1009`.
- Behavior assertions: `3029`.
- Mapped denominator movement: none; this is PHP focused PASS growth against already hydrated upstream SELECT source.

Red-first evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1RepeatedWildcardDynamicTest.php
1 test files, 2273 assertions, 252 failures
```

The failing cases were the repeated wildcard upstream scenarios `select1-1.8.1`, `select1-1.8.3`, and `select1-1.9.2`, all failing with duplicate output-column diagnostics.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1RepeatedWildcardDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3029 assertions, 0 failures
```

Additional focused family check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9107 assertions, 9 failures
```

The remaining header failures are unrelated current-base failures in JSON/scalar/window/predicate/grouped/CTE areas. The directly coupled duplicate-projection assertion was updated from "throws" to the new suffixed duplicate-key behavior.

Non-overlap:

- Does not duplicate the existing SELECT8 LIMIT/OFFSET batch.
- Does not duplicate the prior SELECT core WHERE/GROUP dynamic batch from `20260530T184455Z`; this slice is specifically the upstream `select1.test` repeated wildcard/select-list gap that was still absent.
- Avoids JSON, B-tree, WAL, VFS, PRAGMA, expression-affinity, and source-neutral cleanup surfaces.

Dependency closure:

- No new support component is needed. The slice reuses native PHP `SQLiteSelectSql` and `SQLiteSelectProjection`.

# real-upstream-corpus-pragma-schema-dynamic-20260531T094441Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T094441Z-0`
Base accepted HEAD: `ffcc95ebfcac7bbcd16b24facd07c90559f1565a`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported sections: `pragma-3.20` through `pragma-3.25`.

## Behavior Added

- Added `SQLitePragmaWritableSchemaIntegrityPlan` for schema-level
  `PRAGMA integrity_check` behavior after `writable_schema` rewrites an
  ordinary index into a unique index and an ordinary column into a required
  column, then reloads the schema by rename.
- Added limit handling for `PRAGMA integrity_check(3)`, `(2)`, and `(1)` over
  the same result stream, matching the upstream truncation behavior.
- Added table-scoped clean integrity coverage for `ALTER TABLE ... ADD COLUMN`
  with `NOT NULL DEFAULT 0.25` and `CHECK (1)` additions.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLitePragmaWritableSchemaIntegrityPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaWritableIntegrityDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaWritableIntegrityDynamicTest.php`
  - `1 test files, 35011 assertions, 0 failures`
  - `1004` distinct PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaAttachedIntegrityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaWritableIntegrityDynamicTest.php`
  - `3 test files, 54028 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - no output.

Expected selected PASS movement: `+1004`.
Mapped denominator movement: none; this is additional PASS growth over already
mapped `pragma.test` coverage.

## Non-Overlap

This avoids the already accepted PRAGMA/schema dynamic surfaces for
`pragma_table_info`, `pragma_table_xinfo`, `pragma_table_list`,
`pragma_index_xinfo`, attached integrity checks, cache/page/temp-store PRAGMAs,
trusted-schema function policy, and fault-injection `pragmafault.test` CHECK
constraint recovery. The new slice specifically owns the writable-schema
UNIQUE/required-column integrity diagnostics and the follow-up ADD COLUMN clean
integrity checks from `pragma.test` `3.20` through `3.25`.

## Dependency Closure

No new support component is needed. The slice uses a bounded native PHP planner
under `lanes/libsqlite/src` and generic dynamic tests under
`lanes/libsqlite/tests`.

## Root Harness

Not run - isolated micro-slice.

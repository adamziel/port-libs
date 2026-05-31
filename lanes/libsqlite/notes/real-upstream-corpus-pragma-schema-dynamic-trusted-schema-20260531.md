# Real upstream corpus: PRAGMA trusted_schema schema runtime

Session: `port-dev-sqlite-yield-dyn-real-pragma-20260531T103122Z`
Micro-slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T103122Z-0`
Base accepted HEAD: `1681be96b403cae039655fef5cb4703982266b2d`

## Upstream source truth

- Hydrated upstream file:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test`
- Ported sections:
  `trustschema1-1.100` through `trustschema1-1.160`,
  `trustschema1-1.200` through `trustschema1-1.320`,
  `trustschema1-1.400` through `trustschema1-1.540`,
  `trustschema1-2.100` through `trustschema1-4.2`.
- Covered behavior: `PRAGMA trusted_schema` state parsing and runtime schema
  safety for generated columns, CHECK constraints, DEFAULT expressions,
  partial indexes, expression indexes, views, triggers, direct SQL, TEMP schema
  exceptions, and innocuous built-in `json_extract()`.

## Delta

- Added `SQLiteTrustedSchemaRuntime`, a bounded pure-PHP schema runtime that
  models deterministic, innocuous, and direct-only function flags over main and
  temp schema objects.
- Added `trusted_schema` to the connection boolean PRAGMA state model.
- Added
  `SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedSchema20260531Test.php`
  with `1001` focused TestRunner PASS cases and `9505` behavior assertions.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedSchema20260531Test.php`
  - `1 test files, 9505 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedSchema20260531Test.php | awk '/^PASS /{pass++} END{print "PASS lines: " pass}'`
  - `PASS lines: 1001`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteTrustedSchemaRuntime.php`
  - no syntax errors
- `php -l lanes/libsqlite/src/SQLitePragmaConnectionBooleanState.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedSchema20260531Test.php`
  - no syntax errors
- `php -r '$j=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (json_last_error()) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no whitespace errors

## Non-overlap

This handoff avoids the already accepted PRAGMA/schema clusters for
`auto_vacuum`, `writable_schema` plus integrity checks, file-control pragmas,
schema/index-info expression metadata, result-shape PRAGMAs,
cache-spill/temp-store/table-valued PRAGMA behavior, and
schema/user/data-version behavior. It is limited to upstream
`trustschema1.test` trusted-schema runtime safety.

## Dependency closure

No new external support component is needed. The slice reuses the existing PHP
test harness and adds a bounded native PHP runtime helper under
`lanes/libsqlite/src`.

# Real Upstream Corpus PRAGMA Schema Dynamic 20260530T200636Z-0

Base accepted HEAD: `ab0d9bc9baa20e0418309c1ec67c0447e4a67962`.

Added `SQLiteRealUpstreamPragmaDataVersionDynamicTest.php`, a focused
real-upstream PRAGMA/schema dynamic batch backed by:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  `pragma3-100`, `pragma3-101`, `pragma3-102`, `pragma3-110`,
  `pragma3-130`, `pragma3-140`, `pragma3-160`, `pragma3-180`,
  `pragma3-190`, `pragma3-300` through `pragma3-340`, `pragma3-400`
  through `pragma3-430`, and `pragma3-510A/B`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-8.*` for `schema_version` and `user_version` assignment
  behavior.

Focused behavior:

- `PRAGMA data_version` query shape for `main` and schema-qualified `temp`.
- Writes to `data_version` are ignored.
- Same-connection local commits and schema changes do not advance
  `data_version`, while external/header-observed commits do.
- Transaction rollback restores the previous local PRAGMA version view;
  transaction commit preserves the observed change.
- Empty write transactions do not decrement `data_version`.
- Attached-schema `schema_version` and signed `user_version` assignments stay
  schema-local.

Evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionDynamicTest.php`
  passed with `1 test files, 7804 assertions, 0 failures`.
- Selected PASS-line growth: `+1321` focused TestRunner PASS cases.

Non-overlap:

- This batch does not repeat the accepted PRAGMA schema5 legacy parsing,
  schema invalidation, pragma introspection, table-valued PRAGMA catalog,
  pager-state/cache-size/synchronous, or journal-mode dynamic batches. It owns
  `pragma3.test` data-version connection visibility plus `pragma.test`
  `pragma-8.*` schema/user version assignment coverage.

Dependency closure:

- No new support component is needed. The batch reuses the existing
  `SQLitePragmaSchemaDataVersion` bounded native PHP PRAGMA state component.

# real-upstream-corpus-pragma-schema-dynamic-20260530T193303Z-0

Added `SQLiteRealUpstreamPragmaSchemaDynamicSecondThousandTest.php` with
1,001 focused TestRunner PASS cases and 9,006 behavior assertions.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  `pragma4-5.0`: comment-stripped default expressions in `PRAGMA table_info`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  `pragma4-6.0` through `pragma4-6.3`: `PRAGMA table_list` remains stable
  with view SQL that would fail during normal execution.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  `pragma4-7.1` through `pragma4-7.3`: table-valued PRAGMA rows can be
  joined and preserve parent primary-key, foreign-key, and index metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  `pragma3-300` through `pragma3-430`: shared-cache/WAL `data_version`
  observer semantics, including local writes not bumping the current
  connection and external/header observations bumping observers.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-11.*` plus `pragma4.test` table-valued PRAGMA families:
  function/module/collation/pragma list result shape.

Non-overlap:

This is a fresh `real upstream pragma schema second thousand` namespace. It
does not edit or repeat `SQLiteRealUpstreamPragmaSchemaDynamicThousandTest.php`,
the existing wide/followup/introspection PRAGMA corpus files, runner metadata
rows, WordPress-specific names, or generated fake upstream script ids. The
schemas use generic `second_pragma_*` application names.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSecondThousandTest.php`
  passed with `1 test files, 9006 assertions, 0 failures`.

Dependency closure:

No new support component is needed. The batch reuses existing
`SQLitePragmaSchemaCatalog`, `SQLitePragmaSchemaDataVersion`, and
`SQLiteSchemaRecord` primitives.

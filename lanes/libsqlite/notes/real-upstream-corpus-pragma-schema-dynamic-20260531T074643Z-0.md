# real-upstream-corpus-pragma-schema-dynamic-20260531T074643Z-0

## Scope

Added a focused real-upstream PRAGMA/schema-version corpus file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionExpandedDynamicTest.php`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-8.1.1` through `pragma-8.1.18`
- `pragma-8.2.1` through `pragma-8.2.15`

Covered behavior:

- `PRAGMA schema_version` assignment/readback.
- Defensive mode ignoring schema-version writes.
- Prepared statement expiry after schema-cookie changes.
- Attached-schema `schema_version` isolation.
- `PRAGMA user_version` main/attached isolation.
- Transaction rollback restoring `user_version` values.
- VACUUM-style schema-cookie bump preserving `user_version`.
- Signed `user_version` values.

This is non-overlapping with the accepted table/index/table-valued PRAGMA
shadowing coverage because it targets `pragma.test` 8.x schema/user version
state transitions rather than `pragma4.test`/`pragma5.test` catalog row shape
or table-valued PRAGMA resolution.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionExpandedDynamicTest.php`
  - `1 test files, 36005 assertions, 0 failures`
  - 5001 focused PASS lines.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded
`SQLitePragmaSchemaDataVersion` state model and adds real upstream corpus
coverage around already-implemented native PHP behavior.

## Follow-up

The broader PRAGMA/schema known-red families remain outside this micro-slice:
schema6/thousand-row diagnostics and full release/all-runner parity still need
separate behavior fixes or runner evidence.

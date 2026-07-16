# real-upstream-corpus-pragma-schema-dynamic-20260531T075135Z-0

Implemented a bounded stateful boolean PRAGMA slice from SQLite upstream
`test/pragma4.test` `pragma4-1.*`.

Source behavior ported:

- query forms for connection boolean PRAGMAs return one row/one column;
- assignment forms accept keyword, integer, and parenthesized RHS spellings;
- assignment forms do not produce result columns;
- state is connection-local;
- `PRAGMA foreign_keys = ...` is ignored while a transaction is active and may
  change again after rollback.

Focused test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicBooleanState20260531Test.php`

Dependency closure: no new support component is needed; the slice uses the
existing PHP test harness and a new lane-local native PHP state helper.

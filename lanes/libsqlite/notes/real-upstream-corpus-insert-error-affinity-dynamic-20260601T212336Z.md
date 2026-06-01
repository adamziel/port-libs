# Real Upstream Corpus: INSERT Error/Affinity Dynamic

Slice: `real-upstream-corpus-insert-error-affinity-dynamic-20260601T212336Z`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/insert.test`
- Upstream sections: `insert-1.1`, `insert-1.2`, `insert-1.3`, `insert-1.3b`, `insert-1.3c`, `insert-1.3d`, `insert-1.4`, `insert-1.5`, `insert-1.6`, `insert-1.6b`, `insert-1.6c`, `insert-2.2`, `insert-2.3`, `insert-2.4`, `insert-2.11`, and `insert-2.12`.

Behavior ported:

- `SQLitePDO` now protects `sqlite_*` schema tables from ordinary INSERT writes with SQLite-shaped diagnostics.
- `INSERT ... VALUES` reports upstream-shaped table/column arity errors for omitted or explicit target-column lists.
- Signed numeric literals such as `+10` and `+4.32` are accepted by the INSERT scalar evaluator.
- Omitted ordinary INSERT columns are populated from stored `CREATE TABLE` defaults before row append.
- Explicit and default values are coerced through the existing declared-affinity insert helper.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusInsertErrorAffinityDynamic20260601T212336ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusInsertErrorAffinityDynamic20260601T212336ZTest.php`
  - `1 test files, 6011 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusInsertErrorAffinityDynamic20260601T212336ZTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php lanes/libsqlite/tests/SQLiteInsertSelectConflictTest.php`
  - `6 test files, 6232 assertions, 0 failures`

Delta:

- Adds 1003 focused TestRunner PASS cases from one real upstream insert corpus test file.
- `phpPass` moves `6272894 -> 6273897`.
- Mapped coverage remains `1589 / 1589`.

Non-overlap:

- This slice owns ordinary `INSERT ... VALUES` error/default/affinity behavior from `insert.test` sections 1 and 2.
- It avoids accepted UPSERT/RETURNING, INSERT SELECT conflict, generated DEFAULT VALUES, PDO invalid INSERT persistence, trigger/foreign-key, WAL/VFS, JSON, and b-tree clusters.

Dependency closure:

- No new support component needed; this reuses `SQLitePDO`, `SQLiteInsertValuesSql`, stored `CREATE TABLE` SQL, and `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()`.

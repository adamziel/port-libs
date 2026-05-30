# Vacuum / Backup / Serialize Corpus Next

This slice adds bounded native PHP image-level behavior for selected upstream
SQLite `sqlite3_serialize()`, `sqlite3_deserialize()`, backup API stepping, and
`VACUUM INTO` copy planning semantics.

New implementation:

- `SQLiteVacuumBackupSerializePlan::serialize()` returns a complete database
  byte image plus page-size/page-count/schema metadata.
- `SQLiteVacuumBackupSerializePlan::deserialize()` validates a complete
  database image against the header page count and returns a read-only-capable
  reopened image.
- `SQLiteVacuumBackupSerializePlan::backup()` models full and stepped page-copy
  backup behavior with remaining-page accounting.
- `SQLiteVacuumBackupSerializePlan::vacuumInto()` plans the target image write,
  durable file sync, and directory sync needed by a copied Application database
  maintenance flow.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVacuumBackupSerializePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVacuumBackupSerializePlan.php

php -l lanes/libsqlite/tests/SQLiteVacuumBackupSerializeCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVacuumBackupSerializeCorpusTest.php

php -l lanes/libsqlite/examples/application-vacuum-backup-serialize.php
No syntax errors detected in lanes/libsqlite/examples/application-vacuum-backup-serialize.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVacuumBackupSerializeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

PASS-line delta: `+55` focused PHP TestRunner PASS cases.

Application smoke:

```text
php lanes/libsqlite/examples/application-vacuum-backup-serialize.php
```

The smoke reports a copied `wp_options` database image serialized, reopened
read-only, backed up in a bounded page step, and planned for `VACUUM INTO`
without `ext/sqlite`.

Non-overlap:

This does not repeat accepted VFS sync apply, rollback-journal commit,
super-journal commit, WAL byte truncation, B-tree page relocation/root collapse,
JSON table SELECT/cursor/constraint work, SELECT SQL subqueries, expression
ORDER BY, or the batch3 expression/JSON/subquery/trigger/WAL/schema corpus.

Dependency closure:

No new external support component is required. The slice reuses the existing
native `SQLiteDatabase` page/header reader and records remaining follow-up as
real VFS application of the planned `VACUUM INTO` target write when broader
pager transaction wiring needs it.

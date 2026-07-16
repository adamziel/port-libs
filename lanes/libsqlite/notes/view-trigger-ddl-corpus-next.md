# View / Trigger DDL Corpus Next

Date: 2026-05-27

Slice: `yield-sqlite-view-trigger-ddl-corpus-next`

Behavior added:

- Added `SQLiteViewTriggerDdlCorpus`, a bounded schema-record inspector for upstream-style `CREATE VIEW`, `CREATE TEMP VIEW`, `CREATE TRIGGER`, `CREATE TEMP TRIGGER`, and `CREATE TRIGGER ... INSTEAD OF` metadata.
- Covers view column lists, view source dependencies, trigger timing/event extraction, trigger body statement counts, `NEW` / `OLD` pseudo-column references, TEMP object detection, dangling trigger targets, DROP VIEW / DROP TRIGGER bookkeeping, `IF EXISTS`, case-insensitive object names, and malformed schema-record guardrails.
- Added `application-view-trigger-ddl-corpus.php` for copied Application `sqlite_schema` diagnostics where import tooling preserves autoloaded-option views, view triggers, and drop bookkeeping without requiring `ext/sqlite`.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewTriggerDdlCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS view trigger ddl corpus counts views
...
PASS view trigger ddl corpus trigger by case insensitive name

1 test files, 68 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `1336 -> 1404` from 68 verified new PASS lines.
- `benchmarkDenominator.mapped`: unchanged. This slice adds focused PHP corpus coverage for schema DDL behavior and does not claim a newly hydrated upstream Tcl inventory unit.

Non-overlap:

- Avoids accepted DML trigger conflict inheritance and trigger body execution behavior.
- Avoids accepted schema PRAGMA/DDL corpus by focusing specifically on view/trigger `sqlite_schema` DDL metadata and DROP bookkeeping.
- Avoids accepted parser-level SELECT/JOIN/GROUP/JSON table execution and all accepted WAL/B-tree/VFS clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing `SQLiteSchemaRecord` metadata and native PHP parsing only.

Next:

- Wire view/trigger DDL records into parser-level schema mutation only after this bounded metadata corpus is accepted, preferably with `CREATE VIEW IF NOT EXISTS` / `DROP VIEW` effects on a native schema catalog.

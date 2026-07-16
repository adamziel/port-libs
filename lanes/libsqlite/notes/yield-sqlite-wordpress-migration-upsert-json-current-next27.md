# Application Migration UPSERT JSON Current Next27

Status: focused PHP corpus growth for copied `wp_options` imports that use
`INSERT ... ON CONFLICT(option_name) DO UPDATE` with JSON mutation expressions.

Behavior:

- Added `SQLiteJsonUpsertMigrationPlan`, a bounded row-array executor
  that composes existing UPSERT current-row conflict handling with existing
  `json_set()` / `json_extract()` semantics.
- The plan applies JSON updates to incoming rows before insert and to current
  rows during conflict updates, so repeated source rows in one statement see the
  latest current row.
- Covered excluded JSON paths, current JSON paths, excluded/current columns,
  literal JSON subtype values, SQL scalar literals, `WHERE`-skipped conflicts,
  decoded RETURNING-style rows, malformed inputs, and missing-column guards.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonUpsertMigrationCurrentNext27Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

```sh
php lanes/libsqlite/examples/application-json-upsert-migration-current-next27.php --self-test
```

PASS delta: +64 focused PHP PASS lines. `lane-status.json` `phpPass` moves
from `9342` to `9406`.

Non-overlap:

This slice avoids accepted UPSERT trigger/FK yield behavior, UPSERT RETURNING
multi-row projection-only behavior, JSON table cursor/source/constraint work,
SELECT derived-table Application staging, WAL/VFS rollback/checkpoint work, and
B-tree page/freeblock/freelist clusters. It is specifically current-row JSON
mutation during Application option migration UPSERT.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local
`SQLiteUpsertDoUpdateWherePlan`, `SQLiteJsonMutation`, and `SQLiteJsonExtract`
primitives.

Next:

Broaden from bounded row-array planning into parser-level `INSERT ... ON
CONFLICT DO UPDATE SET option_value=json_set(...)` SQL text execution when the
SQL insert/update executor owns that surface.

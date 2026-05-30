# yield-sqlite-json-table-update-from-recursive-current-next36

## Behavior

This slice covers a bounded executor path where `UPDATE ... FROM` consumes a
`WITH RECURSIVE` source whose anchor rows are produced from current
`wp_options` JSON payloads through `json_tree()`. It verifies that the existing
parser/executor composition preserves SQLite current-source behavior:

- recursive CTE anchor rows can join current target JSON through `json_tree()`;
- duplicate source rows for one target collapse to the last matched update;
- recursive "current/next" rows keep stable source order and feed assignments;
- `UPDATE OR REPLACE` deletes a conflicting current row when a recursive JSON
  source renames a unique `option_name`;
- missing JSON roots produce an empty source and leave target rows unchanged.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableUpdateFromRecursiveCurrentNext36Test.php
```

Expected focused movement: 51 new focused PASS lines in a new lane-scoped test
file. The Application smoke is:

```sh
php lanes/libsqlite/examples/application-json-table-update-from-recursive-current-next36.php
```

## Non-Overlap

This does not repeat accepted standalone `UPDATE FROM` duplicate-source/current
conflict behavior, parser-level JSON table SELECT sources, JSON table cursor
iteration, JSON hidden/visible constraint extraction, recursive upsert/trigger
yield behavior, or recursive CTE cycle coverage. The new coverage is the
combined executor path where a recursive CTE seeded by `json_tree()` feeds
`UPDATE ... FROM` current-row mutations.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP `SQLiteSelectSql`, `SQLiteJsonTablePlan`, and `SQLiteUpdateFromSql`
components; activation evidence is the focused PHP test and local Application
smoke above.

## View/Trigger Name Resolution Edge Next14

Slice: `yield-sqlite-view-trigger-name-resolution-edge-next14`

This slice adds bounded upstream-style schema name-resolution coverage for
view and trigger edges that were not covered by the existing view/trigger DDL
metadata corpus:

- Trigger targets resolve to table/view records before checking `OLD` and
  `NEW` pseudo-column references.
- `INSTEAD OF` triggers resolve against explicit view column lists.
- Views without explicit column lists derive output names from SELECT aliases.
- TEMP triggers prefer TEMP shadowed targets, while main triggers continue to
  resolve the main schema object.
- Missing `NEW` / `OLD` references are surfaced as unresolved schema metadata
  instead of being silently accepted.

Added files:

- `src/SQLiteViewTriggerNameResolution.php`
- `tests/SQLiteViewTriggerNameResolutionTest.php`
- `examples/application-view-trigger-name-resolution.php`

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewTriggerNameResolutionTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-view-trigger-name-resolution.php
```

Status delta:

- `phpPass`: +56 verified PASS lines from the focused TestRunner file.
- `benchmarkDenominator.mapped`: unchanged; no new upstream manifest unit is
  claimed for this focused PHP behavior slice.

Dependency closure:

- No new support component is needed. The slice reuses existing schema-record
  objects and bounded PHP SQL-text inspection helpers.

Non-overlap:

- Avoids accepted view/trigger DDL catalog metadata, ALTER trigger/view rename
  rewriting, trigger execution ordering, trigger/FK interaction, SELECT SQL
  text dispatch, JSON table source/constraint/cursor work, VFS/WAL/B-tree
  accepted clusters, and recent status/provenance-only suite work. This slice
  specifically targets trigger target and `OLD`/`NEW` column name resolution
  against table/view schema records.

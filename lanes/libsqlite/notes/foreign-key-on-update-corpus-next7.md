# Foreign Key ON UPDATE Corpus Next7

Slice: `yield-sqlite-foreign-key-on-update-corpus-next7`

This patch extends the existing bounded `SQLiteForeignKeyDeferredCascadePlan`
with upstream-style `ON UPDATE` action handling for parent-key rewrites:
`CASCADE`, `SET NULL`, `SET DEFAULT`, `NO ACTION`, and `RESTRICT`. It is
disjoint from the accepted deferred `ON DELETE` cascade corpus and does not
repeat accepted WAL, VFS, JSON table, B-tree, SELECT SQL, or Unicode GLOB
clusters.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
46 PASS lines
1 test files, 46 assertions, 0 failures
```

Shared-class regression check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeCorpusTest.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCorpusTest.php
Focused test run: 2 selected test files (root lock skipped)
84 PASS lines
2 test files, 84 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `2017 -> 2063` from the verified 46 new PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP corpus growth,
  not a newly mapped upstream inventory unit.

Application smoke:

- `php lanes/libsqlite/examples/application-foreign-key-on-update.php`
  reports copied option-group key renumbering cascading into related option
  rows without requiring ext/sqlite.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  row-array execution helpers and adds no external service, native extension,
  or shared support-library dependency.

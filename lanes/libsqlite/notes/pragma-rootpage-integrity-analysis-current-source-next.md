# pragma-rootpage-integrity-analysis-current-source-next

This slice adds `SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext`, a
structured current-source companion to existing `PRAGMA integrity_check`
rootpage diagnostics. It does not change the accepted integrity-check message
surface; it exposes repair/import analysis rows for schema root ownership.

Behavior covered:

- table/index rootpage rows with page type, page flag, pointer-map type/parent,
  pointer-map page, and ready/blocking summary;
- duplicate schema records reusing the same positive rootpage;
- rootpages on the freelist, beyond the database image, negative, or missing;
- table records pointing at index b-trees and index records pointing at table
  b-trees;
- auto-vacuum root pointer-map conflicts and largest-root/header mismatches;
- view/trigger rootpage zero rows ignored for repair gating.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 181 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-rootpage-integrity-analysis-current-source-next.php
```

Dashboard delta: `phpPass` increases by 37 PASS lines, from `42491` to
`42528`. Mapped upstream coverage remains `604 / 1589`; this is focused native
PHP current-source coverage and does not claim fresh upstream runner execution.

Non-overlap: this avoids batch106 PRAGMA recursive foreign-key catalog output,
PRAGMA quickcheck/index_xinfo integrity, pointer-map/FK integrity pagination,
foreign-key integrity pointer-map checks, table-scoped integrity pagination,
and accepted rootpage `integrity_check` message behavior. The new surface is
structured rootpage analysis for current schema repair decisions.

Dependency closure: no new support component is needed. The slice reuses
existing SQLite header, schema-record, b-tree page, freelist, pointer-map, and
PRAGMA integrity primitives already present in `lanes/libsqlite/src`.

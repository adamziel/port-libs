# real-upstream-corpus-pragma-schema-dynamic-20260530T202843Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T202843Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- Upstream section: `pragma4-1.*` result-column arity for PRAGMA statements.

Implemented:

- Added `SQLitePragmaResultColumnPlan` for generic PRAGMA statement-shape planning.
- Added `SQLiteRealUpstreamPragma4ResultColumnDynamicTest.php` with 1,028 distinct TestRunner PASS cases and 5,140 behavior assertions.
- Ported upstream `pragma4-1.*` behavior:
  - read-form PRAGMAs expose one result column;
  - assignment and call write forms expose zero result columns;
  - `shrink_memory` and `case_sensitive_like` expose zero columns even without a RHS;
  - schema-qualified and case-varied PRAGMA names normalize to the same statement shape.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePragmaResultColumnPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePragmaResultColumnPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragma4ResultColumnDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragma4ResultColumnDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma4ResultColumnDynamicTest.php
1 test files, 5140 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```

Expected dashboard movement:

- `phpPass`: `612306 -> 613334` for the 1,028 verified focused PASS lines.
- Mapped coverage remains `1472 / 1589`; this ports behavior from an already mapped upstream PRAGMA file.

Non-overlap:

- This does not repeat accepted PRAGMA table/index/FK schema metadata, schema/data-version, `pragma5` function/module/pragma-list introspection, schema3 invalidation, schema5 legacy constraints, schema6 rowid layout, or earlier `pragma4` table-valued catalog rows.
- This owns the previously separate `pragma4-1.*` statement result-column arity surface.

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP test runner and adds a bounded native PRAGMA planner.

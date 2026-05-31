# real-upstream-corpus-select-core-dynamic-20260531T043012Z-0

Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-5`: parenthesized `LEFT JOIN ... USING(a)` groups joined by an
  outer `ON` clause.
- `selectD-6`: right-side null-extension from the inner parenthesized
  `LEFT JOIN ... USING(a)` group.
- `selectD-7`: explicit `t1.*, t2.*, t3.*, t4.b` projection across the same
  parenthesized join tree.

Implemented focused coverage:

- Added `SQLiteRealUpstreamSelectDUsingLeftJoinDynamicTest.php`.
- The file adds one upstream-source citation case plus 1,250 dynamic
  TestRunner cases. Each dynamic case varies the table keys, the left
  `USING(a)` match/miss, and the right `USING(a)` match/null-extension while
  preserving the upstream parenthesized join shape:
  `(t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a)) ON t1.a=t3.a-111`.
- Focused assertion count: `7,505`.
- Focused PASS-line count: `1,251`.

Non-overlap:

- Extends upstream `selectD-5` through `selectD-7` left-join/USING behavior.
- Avoids accepted `selectD` parenthesized comma FROM and nested `ON` join
  coverage, `selectD-4.1` derived aggregate coverage, `selectC` alias
  coverage, grouped SELECT text, expression `ORDER BY`, JSON table
  source/cursor/constraint work, and storage/VFS/B-tree surfaces.
- Does not add metadata-only runner rows or generated fake upstream script IDs.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP
  `SQLiteSelectSql` parser/executor and the hydrated upstream SQLite test
  checkout as source truth.

Verification:

- Initial red attempt: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDUsingLeftJoinDynamicTest.php` failed because the local dynamic fixture's noise rows also satisfied the upstream outer `ON` predicate. The fixture was corrected to keep noise rows outside that predicate.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectDUsingLeftJoinDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDUsingLeftJoinDynamicTest.php`
  - Result: `1 test files, 7505 assertions, 0 failures`
  - PASS lines: `1251`
- `git diff --check -- lanes/libsqlite`

Root harness:

- Not run - isolated micro-slice.

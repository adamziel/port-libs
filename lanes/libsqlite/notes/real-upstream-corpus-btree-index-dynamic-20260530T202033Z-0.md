# Real Upstream B-tree/Index Dynamic Corpus

Micro-slice: `real-upstream-corpus-btree-index-dynamic-20260530T202033Z-0`

Accepted base: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr2.test`

Added focused TestRunner coverage to the existing B-tree/index dynamic corpus:

- `index3.test` sections `index3-2.1` through `index3-2.5`: 1,200 quoted-identifier, autoindex catalog-name, collation, sort-order, and lookup compatibility cases.
- `indexexpr1.test` sections `indexexpr1-110` through `indexexpr1-260`: rowid and WITHOUT ROWID expression-index lookup, range, ORDER BY, and covering-index cases.
- `indexexpr2.test` sections `indexexpr2-3.4` and `indexexpr2-4.110` through `indexexpr2-4.130`: expression-index NOCASE ordering and UPDATE recomputation/refcount cases.

Focused count:

- Before this patch, `SQLiteBTreeIndexDynamicCorpusPlanTest.php` returned 2,830 TestRunner cases.
- After this patch, it returns 5,230 TestRunner cases.
- Net focused PASS-case growth: +2,400 distinct real upstream behavior cases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
- Result: `1 test files, 65163 assertions, 0 failures`
- PASS lines: 5,230

Non-overlap:

- This extends the existing real upstream B-tree/index dynamic corpus with `index3`, `indexexpr1`, and `indexexpr2` sections.
- It does not repeat accepted page relocation, root collapse, overflow freelist release, index-interior merge, JSON/VFS/WAL, SELECT SQL text, or source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed.
- The coverage reuses lane-local B-tree/index page, record, planner, partial-index, quoted-identifier, expression-index, collation, write-order, and cursor-case helpers.

# Real Upstream Corpus Window Dynamic Range

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T015803Z-0`

Base accepted HEAD: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  - sections `19.2.*`, `19.3.*`: `RANGE` offset frames over numeric and text order keys
  - sections `20.2.*`, `20.3.*`: `RANGE` offset frames over NULL, numeric, and text order keys
  - section `66`: fractional `RANGE` offsets over large integer order keys
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
  - section `1`: NULL peer groups with `NULLS FIRST`/`NULLS LAST` and descending order
  - section `2`: mixed NULL, numeric, text, and blob-like peer fallback for `RANGE` offsets

Behavior change:

- `SQLiteVdbeWindowAggregateCursor` no longer rejects nonnumeric `RANGE` order keys.
- Bounded `RANGE` offsets now use SQLite's peer-only fallback when the current row or candidate row is nonnumeric, while preserving numeric offset semantics for numeric pairs, descending order, and explicit NULL placement.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowRangeMixedTypeRealCorpusTest.php`
  - `1 test files, 1024 assertions, 0 failures`
  - `1005` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicFrameCorpusTest.php lanes/libsqlite/tests/SQLiteWindowRangeMixedTypeRealCorpusTest.php lanes/libsqlite/tests/SQLiteWindowRangeNullsLargeCorpusTest.php`
  - `3 test files, 23778 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteWindowRangeMixedTypeRealCorpusTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- Avoids accepted lead/lag/ntile/window4E, window pushdown, dynamic frame, boolean-view, RANGE NULL placement, and recent window groups/range coverage by fixing the shared `RANGE` offset cursor behavior for mixed storage classes from real `window1.test` and `windowB.test`.

Dependency closure:

- No new support component needed. This reuses the existing VDBE sort comparator and window aggregate cursor.

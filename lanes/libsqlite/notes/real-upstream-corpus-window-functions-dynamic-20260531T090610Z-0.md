# real-upstream-corpus-window-functions-dynamic-20260531T090610Z-0

Base accepted HEAD: `1f995af210b97149d5ecea17c4a5ed750856854a`.

Implemented a real upstream window-function dynamic batch under
`lanes/libsqlite/tests/SQLiteRealUpstreamWindow8FractionalRangeDynamic20260531Test.php`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
  sections `3.1-3.3`: ASC REAL `RANGE` frames with integer offsets.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
  sections `3.4-3.6`: DESC REAL `RANGE` frames with integer offsets.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
  sections `3.7-3.13`: fractional, unbounded, and shorthand REAL `RANGE`
  offsets.

Focused movement:

- Added 1,015 distinct TestRunner PASS cases.
- Added 9,028 focused behavior assertions.
- No production source changes.
- Expected selected `phpPass` movement: `2835919 -> 2836934`.
- Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth
  over already mapped upstream corpus files.

Non-overlap:

This owns upstream `window8.test` section 3 REAL `ORDER BY a RANGE` semantics.
It avoids accepted `window8` GROUPS frame matrices, `window8` NULL RANGE cursor
cases, `windowA` DESC/NULL range batches, `windowE` numeric/collation range
batches, `windowC` separator batches, `window9` subquery/collation batches,
JSON/WAL/VFS/B-tree surfaces, grouped SELECT text, expression ORDER BY, and
metadata-only upstream runner rows.

Dependency closure:

No new support component is needed. The batch reuses
`SQLiteWindowFunction::aggregateOrderedRangeValues()` and validates it with a
lane-local independent REAL RANGE oracle.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow8FractionalRangeDynamic20260531Test.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8FractionalRangeDynamic20260531Test.php`
  -> `1 test files, 9028 assertions, 0 failures`; 1,015 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `1 test files, 3 assertions, 0 failures`.
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`
  -> `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite`
  -> clean.

Root harness: not run - isolated micro-slice.

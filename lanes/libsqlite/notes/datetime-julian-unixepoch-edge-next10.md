# Date/Time Julian/Unixepoch Edge Next10

Slice: `yield-sqlite-date-time-julian-unixepoch-edge-next10`

Behavior added:

- Numeric text time-values now follow SQLite's default Julian day interpretation.
- The explicit `julianday` modifier is accepted as a time-value interpretation modifier.
- The `auto` modifier now chooses Julian day for `0.0..5373484.499999` and Unix timestamp outside that range.
- Fractional Unix timestamps preserve microseconds for `%f`, `%J`, and downstream Julian day conversion, including negative fractional timestamps.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDateTimeJulianUnixepochEdgeTest.php`
- Result: `1 test files, 34 assertions, 0 failures` with 34 PASS lines.
- `php lanes/libsqlite/examples/application-datetime-julian-unixepoch-edge.php --self-test`
- Result: passed; copied Application cron rows normalize Julian day, Unix timestamp, and `auto` timestamp sources.

Dashboard delta:

- `phpPass`: `3236 -> 3270` (`+34` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.

Non-overlap:

- This slice avoids accepted strftime modifier corpus coverage by targeting Julian day / Unix timestamp time-value interpretation boundaries.
- It does not touch accepted SELECT SQL text, JSON table, WAL/VFS, B-tree, Unicode GLOB, or storage transaction clusters.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP scalar date/time implementation.

Next task:

- Continue with non-overlapping high-yield libsqlite work: SQL planner/executor gaps, JSON planner/JSONB behavior, WAL/pager durability, B-tree freelist/pointer-map materialization, encoding/collation, or a distinct suite blocker.

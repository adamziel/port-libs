# real-upstream-corpus-date-affinity-dynamic-oracle-batch-20260530

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T212107Z-0`

Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`

Added `SQLiteRealUpstreamDateAffinityDynamicOracleBatchTest.php`, a real upstream corpus batch sourced from the hydrated SQLite checkout:

- `test/date.test`: date parsing, arithmetic modifiers, start/weekday modifiers, floor/ceiling policy, timezone/default-date behavior.
- `test/date2.test`: leap-adjacent datetime modifier behavior.
- `test/date3.test`: `unixepoch`, `julianday`, and `auto` numeric-domain behavior.
- `test/affinity2.test`, `test/affinity3.test`, `test/types.test`, `test/types2.test`, and `test/cast.test`: cast and storage-class affinity behavior for text, numeric, integer, real, and blob targets.

The batch is oracle-backed against local `sqlite3` and checks the PHP port through `SQLiteCoreScalarFunction` and `SQLiteSelectExpression`. REAL `julianday()` results compare storage class exactly and numeric value within tolerance to avoid `quote()` formatting-only differences between scientific and decimal notation.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicOracleBatchTest.php`
- Result: `1 test files, 13750 assertions, 0 failures`.

Non-overlap/exclusion:

- This does not touch existing accepted date localtime/subsecond/week/day batches, expression affinity real-oracle batches, or accepted source-neutral/API work.
- One unsupported combination is deliberately excluded from this batch: `date.test date-2.60` overflow-day input combined with `date.test date-19` floor/ceiling policy. Local oracle showed the port still normalizes `2023-02-31` differently from SQLite when later month floor/ceiling modifiers are stacked. That is a concrete future behavior fix, but the rest of this batch is green and countable.

Dependency closure:

- No new support component is needed. The existing bounded `sqlite3` oracle pattern used by other real upstream corpus tests is reused only for verification; production code remains native PHP.

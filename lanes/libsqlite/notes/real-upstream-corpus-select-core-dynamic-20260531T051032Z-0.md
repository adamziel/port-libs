# real-upstream-corpus-select-core-dynamic-20260531T051032Z-0

Added `SQLiteRealUpstreamSelectHWideCompoundDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- Ported sections: `selectH-1.2` and `selectH-2.1`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHWideCompoundDynamicTest.php`
- Result: `1 test files, 4008 assertions, 0 failures`
- Focused PASS growth: `+1003` TestRunner cases.

Behavior covered:

- `selectH-1.2` wide compound subquery row shape with star-expanded arms and an outer `WHERE c60=60` filter binding to the source column, not to a trailing computed projection.
- `selectH-2.1` compound subquery `ORDER BY b` binding to an explicit projection after `*`, while the outer query projects a different alias.
- The upstream Tcl side-effecting `counter()` assertion is not reimplemented as a PHP SQL user function; this batch ports the observable SELECT row-shape behavior through the existing native `SQLiteSelectSql` executor.

Non-overlap:

- This does not repeat accepted grouped SELECT text, expression `ORDER BY`, JSON table SELECT source/cursor/constraint behavior, `select5` aggregate/group batches, `selectC` distinct-derived batches, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectH.test` is already part of the hydrated upstream inventory; this is PHP PASS-line growth over mapped real upstream SELECT corpus behavior.

Dependency closure:

- No new support component is needed. The batch reuses the existing native `SQLiteSelectSql` compound subquery execution path.

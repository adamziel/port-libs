# real-upstream-corpus-upsert-returning-dynamic-20260531T051724Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Upstream section: `upsert4-9.1`, the trigger-maintained histogram where an
  `AFTER INSERT` trigger on `v` inserts into `hist` and uses
  `ON CONFLICT(x) DO UPDATE SET cnt=cnt+1`.

Patch:

- Added `SQLiteRealUpstreamUpsert4TriggerHistogramDynamicYieldTest.php`.
- The test ports the upstream trigger UPSERT behavior into a 4096-stream
  dynamic corpus. Each stream uses a deterministic six-position source over
  values `1`, `4`, `5`, and `9`, then repeats those positions to produce a
  twelve-row outer insert stream.
- Focused assertions verify final histogram frequencies, one inner UPSERT
  result per outer insert, per-key occurrence counts, and before-insert probe
  ordering before insert/update yield trace events.

Non-overlap:

- Existing accepted UPSERT RETURNING coverage already covers
  `returning1.test` row streams, `upsert3`/`upsert4` excluded-alias behavior,
  fixed `upsert4-9.1` examples, target admission, conflict arm ordering, and
  statement-current RETURNING.
- This batch owns the larger `upsert4-9.1` six-position dynamic trigger
  histogram streams. It does not touch the parked excluded-alias regression or
  production SQL parsing/source APIs.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4TriggerHistogramDynamicYieldTest.php`
  - `No syntax errors detected`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4TriggerHistogramDynamicYieldTest.php`
  - `1 test files, 122884 assertions, 0 failures`
  - `16386` TestRunner PASS lines

Dependency closure:

- No new support component is needed. The corpus reuses the existing native
  `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` helper.

Expected dashboard movement:

- Countable as focused PASS-line growth if accepted: `+16386` PASS lines.
- Mapped denominator coverage remains `1589 / 1589`.

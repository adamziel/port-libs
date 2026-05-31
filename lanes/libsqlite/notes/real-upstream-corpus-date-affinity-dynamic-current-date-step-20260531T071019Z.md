# real-upstream-corpus-date-affinity-dynamic-20260531T071019Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicCurrentDateStep20260531T071019ZTest.php` as an additive real upstream date/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario names: `date-15.1` and `date-15.2`.

Focused behavior:

- 1024 dynamic statement-step rows verify that `current_date`, `current_time`, `current_timestamp`, `date('now')`, `time('now')`, `datetime('now')`, `strftime(...,'now')`, `datetime('now','subsec')`, `unixepoch('now','subsecond')`, and repeated `julianday('now')` all read from one stable step timestamp.
- A generic application audit sample checks stable date/time key materialization from the same statement snapshot.

Non-overlap:

- This extends accepted `date-15` stable-step coverage to `current_date`, `current_time`, strftime aliases, and subsecond unixepoch in the same statement snapshot.
- It avoids accepted `date8` now-modifier matrices, `date20` no-round fractional truncation, `date4` strftime row ranges, `date5` Gregorian-cycle rows, date2/date3 deterministic schema/auto batches, floor/ceiling/month-matrix coverage, timezone/localtime coverage, and expression-affinity shards.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteCoreScalarFunction::statementDateTimeResults()` and native current date/time scalar dispatch.

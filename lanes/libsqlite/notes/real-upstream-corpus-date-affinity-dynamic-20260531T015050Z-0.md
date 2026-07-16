# real-upstream-corpus-date-affinity-dynamic-20260531T015050Z-0

Status: focused real-upstream corpus test growth for SQLite date/time affinity.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `date2-500`: `mods` table cross-joined with four generated Julian-day rows, indexed by `datetime(y,m) IS NOT NULL`.
- `date2-510` and `date2-520`: `localtime` and `utc` remain non-deterministic in expression-index contexts.

Coverage added:

- `SQLiteRealUpstreamCorpusDateAffinityDynamicDate2ModifierIndex20260531T015050ZTest.php`
- 85 real upstream modifier-row PASS cases with 6 assertions each, comparing native `datetime(y,m)` dispatch to the local `sqlite3` oracle and checking real-affinity, type, partial-index truth, and deterministic-admission behavior.
- 4 supporting PASS cases for source citation, nondeterministic guard errors, generic retention rollup, and non-overlap ownership.
- Focused assertion count: 527 assertions in the new test file.

Non-overlap:

- Avoids accepted `date2-300`/`date2-331` full-table range rows, `date2-600..620` deterministic schema guards, `date3` unixepoch/auto/modifier-placement loops, `date4` strftime batches, `date5` Gregorian-cycle rows, expression-affinity batches, and source-neutral cleanup.
- This slice owns only the real upstream `date2-500` modifier index rows plus the `date2-510`/`date2-520` nondeterministic index guards.

Dependency closure:

- No new support component is needed. The test reuses native `SQLiteCoreScalarFunction` date/time dispatch and the local `sqlite3` oracle pattern already used by real upstream dynamic corpus tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate2ModifierIndex20260531T015050ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate2ModifierIndex20260531T015050ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

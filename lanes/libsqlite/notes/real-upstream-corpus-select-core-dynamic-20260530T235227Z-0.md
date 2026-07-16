# real-upstream-corpus-select-core-dynamic-20260530T235227Z-0

Base accepted HEAD: `c18695783d58d6f8245967de682828c93b145ece`

Added `SQLiteRealUpstreamSelect7HavingAffinityDynamicTest.php` as an additive
real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`
- `select7-7.7`: grouped `HAVING a<b` compares a `TEXT` column with an `INT`
  column using declared affinity and returns the text row.

Focused coverage:

- `1,002` distinct TestRunner PASS cases.
- `5,008` focused behavior assertions.

Non-overlap:

- This owns the previously excluded `select7-7.7` text-affinity `HAVING a<b`
  behavior.
- It does not repeat accepted `select7-7.2`, `select7-7.4`, `select7-7.5`,
  `select7-7.6`, prior `select1` through `select6`, `select8`, `select9`,
  `selectA` through `selectH` batches, grouped SELECT text, expression
  `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only
  runner rows.
- Mapped denominator remains unchanged because `select7.test` is already in
  the hydrated upstream manifest coverage. This is PASS-line and behavior
  assertion growth only.

Dependency closure:

- No new support component is needed. The batch reuses existing lane-local
  `SQLiteSelectSql`, `SQLiteSelectPredicate`, and declared column-affinity
  comparison metadata.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect7HavingAffinityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect7HavingAffinityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

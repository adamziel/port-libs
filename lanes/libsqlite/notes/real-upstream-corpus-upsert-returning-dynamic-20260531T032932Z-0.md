# real-upstream-corpus-upsert-returning-dynamic-20260531T032932Z-0

Status: ready for clean integration as focused PASS-line growth.

Implemented native UPSERT conflict-target admission in
`SQLiteUpsertDoUpdateWherePlan::admitConflictTarget()` for expression terms,
collations, partial-index predicates, composite target order, and generalized
conflict-arm target metadata.

Real upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-130` through `upsert1-140`: collation-sensitive conflict target
    admission.
  - `upsert1-200` through `upsert1-210`: expression-index target admission and
    expression mismatch rejection.
  - `upsert1-300` through `upsert1-320`: partial-index predicate admission and
    mismatch rejection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - `upsert3-110` through `upsert3-140`: composite target admission, including
    reversed target term order.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `1.*` generalized UPSERT arm ordering over admitted conflict targets.

Focused assertion/PASS evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTargetAdmissionDynamicTest.php`
- Result: `1 test files, 2956 assertions, 0 failures`
- PASS lines: `1010`
- Expected selected movement: `1854061 -> 1855071 pass / 0 fail`
- Mapped denominator movement: none, remains `1589 / 1589`

Non-overlap:

- Existing UPSERT/RETURNING real-upstream batches cover row streams, triggers,
  aliases, catch-all arms, statement-current RETURNING recomputation, and
  projection behavior.
- This slice covers the pre-execution `ON CONFLICT` target admission gate for
  expression indexes, partial indexes, collations, and composite targets.

Dependency closure:

- No new support component is needed. The slice reuses native PHP UPSERT
  planning and adds bounded metadata admission for conflict targets.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTargetAdmissionDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTargetAdmissionDynamicTest.php`
- Additional required guard/diff checks are recorded in the final lane report.

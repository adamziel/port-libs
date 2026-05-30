# real-upstream-corpus-upsert-returning-dynamic-20260530T203414Z-0

Status: blocked by accepted overlap and hard throughput floor.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Current accepted overlap found:

- `SQLiteRealUpstreamUpsertReturningDynamicTest.php` and related dynamic files already cover the high-volume `upsert4.test` / `upsert5.test` UPSERT arm-order, target-priority, redundant-conflict, catch-all, schema-variant, and RETURNING row-image surfaces.
- `SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php` already ports the parser-level CTE `VALUES` + `INSERT INTO ... SELECT ... ON CONFLICT ... RETURNING` behavior from `upsert2-200`, `upsert2-201`, `upsert2-210`, `upsert1-101`, `returning1-4.5`, and `returning1-17`.
- `SQLiteRealUpstreamCorpusUpsertReturningDynamicConflictTargetBatchTest.php` already covers `upsert1.test` conflict-target matching/rejection, `returning1.test` UPSERT RETURNING row images, and `upsert5.test` DO NOTHING arm suppression at high volume.
- `SQLiteRealUpstreamCorpusUpsertReturningDynamicCorrelatedDeleteTest.php` already provides a large focused RETURNING-correlated-delete corpus from `returning1.test`.

Why this slice is blocked:

- The remaining non-overlapping cases in this named UPSERT/RETURNING dynamic domain are narrow follow-ups such as `upsert4.test` alias/table-name edge cases around a real table named `excluded`, `upsert2.test` trigger lifecycle rows, and `returning1.test` view/trigger/FK error-ordering cases.
- Those cases do not form a clean >=1,000 distinct TestRunner PASS case or >=5,000 behavior assertion batch against the existing bounded UPSERT/RETURNING helpers without adding repetitive bookkeeping assertions.
- The trigger/view/FK remainder needs a separate trigger/view executor batch, not another small UPSERT/RETURNING dynamic corpus patch.

Next larger batch to try:

- Create a separate real upstream trigger/view RETURNING batch from `upsert2.test` sections `300-420` and `returning1.test` sections `18-20`, backed by native trigger lifecycle and correlated RETURNING execution. That batch can plausibly unlock thousands of assertions if implemented as a shared trigger/view RETURNING executor rather than as metadata-only tests.

Verification for this blocker note:

- No PHP source or PHP test files changed.
- `git diff --check -- lanes/libsqlite` should remain the only required local check for this note-only blocked handoff.

Dependency closure:

- No new support component is proposed for this blocked slice. The next viable batch would reuse existing UPSERT/RETURNING helpers and add or extend a bounded native trigger/view RETURNING executor.

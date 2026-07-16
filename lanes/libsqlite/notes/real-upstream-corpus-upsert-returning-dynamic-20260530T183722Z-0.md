# real-upstream-corpus-upsert-returning-dynamic-20260530T183722Z-0

Status: blocked by non-overlap and hard throughput floor.

Attempted upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - Existing accepted/current coverage already owns `upsert1-320`, `upsert1-400`, `upsert1-500`, `upsert1-700` through `upsert1-780`, `upsert1-800`, `upsert1-1100`, `upsert1-1200`, and `upsert1-1300`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - Existing accepted/current coverage already owns `upsert2-100`, `upsert2-110`, `upsert2-200`, `upsert2-201`, `upsert2-210`, and trigger lifecycle sections `upsert2-300` through `upsert2-421`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - Existing accepted/current coverage already owns `upsert3-110`, `upsert3-120`, `upsert3-130`, `upsert3-140`, `upsert3-200`, and `upsert3-210`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - Existing accepted/current coverage already owns target analysis and partial-index sections `upsert4-1.*` through `upsert4-5.0`, REPLACE-precedence sections `upsert4-6.*`, excluded-name/alias sections `upsert4-7.*` and `upsert4-8.*`, and trigger histogram section `upsert4-9.1`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Existing accepted/current coverage already owns generalized multi-arm UPSERT ordering scenarios `upsert5-1.$tn.100` through `upsert5-1.$tn.505`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Existing accepted/current coverage already owns early INSERT/UPDATE/DELETE RETURNING projection, sections `returning1-4.2`, `returning1-4.5`, trigger/TEMP ordering, section 17 duplicate UPSERT RETURNING rowids, and section 20 advancing DELETE RETURNING subqueries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
  - Remaining non-overlapping content is one OOM faultsim UPSERT statement over `ON CONFLICT(b, c) DO UPDATE SET d=d+1`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test`
  - Remaining non-overlapping content is one OOM subquery-column-count RETURNING diagnostic and one virtual-table RETURNING faultsim path.

Why this is blocked:

- The current slice is a `real-upstream-corpus-*` throughput lane with a hard handoff floor of at least 1,000 distinct focused TestRunner PASS cases, 5,000 behavior assertions, a named blocker that unlocks at least 2,000 PASS cases or 10,000 assertions, or real mapped-denominator movement.
- The remaining non-overlapping UPSERT/RETURNING material in the assigned domain is faultsim-specific and far below that floor. Porting it as a small focused PHP test would produce a convenience-sized patch, not an integration-competitive corpus handoff.
- Adding duplicated assertions around already covered `upsert1` through `upsert5` or `returning1` sections would violate the real upstream corpus and non-overlap rules.

Next larger batch to try:

- Reassign a broader real-corpus lane that combines untouched fault-adjacent DML families beyond UPSERT/RETURNING, such as `returningfault.test`, `upsertfault.test`, `insertfault.test`, `updatefault.test`, `deletefault.test`, and trigger/FK fault files, then port their shared fault/error-order behavior into one coherent PHP fault-diagnostics batch.
- Alternatively, move this worker to a `source-neutral-*` cleanup or a fresh non-overlapping upstream family with enough remaining sections to clear the throughput floor.

Verification:

- No PHP source or test files were changed because the valid outcome for this slice is a blocker note, not a small green patch.
- Root harness not run; isolated micro-slice only.

Dependency closure:

- No new support component was added. A future fault-diagnostics batch would need a bounded native fault-injection/error-order helper only if it can cover multiple upstream fault files at throughput scale.

# real-upstream-corpus-trigger-fkey-dynamic-20260531T012117Z-0

Base accepted HEAD: `9c01a66e5dc81444d443e06defaf90851a98b56e`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`
- Sections: `triggerG-100`, `triggerG-110`, and `triggerG-200`.

Behavior ported:

- Recursive `AFTER INSERT` trigger programs that insert the next `t3` row while `new.c < max`.
- Trigger-body `INSERT INTO ... SELECT` from an indexed table must run for each recursive trigger invocation. The accepted behavior specifically guards the historical OP_Once failure where recursive trigger subprogram SELECT bodies were incorrectly suppressed.
- Both single-source trigger SELECT rows (`new.c*100+a`) and join-source rows (`new.c*10000+xx.a*100+yy.a`) are covered.

Focused count:

- `SQLiteRealUpstreamTriggerFkeyDynamicRecursiveOnce20260531Test.php`
- 1,005 distinct TestRunner PASS cases.
- 16,512 behavior assertions.

Non-overlap:

- Does not repeat existing `fkey2` nocase/replace, `fkey6` defer/restrict, count_changes, action-matrix, or prior trigger/FK dynamic batches.
- Does not add metadata-only denominator rows or fabricated upstream script ids.

Dependency closure:

- No new support component needed. The slice extends the existing generic `SQLiteDynamicTriggerForeignKeyPlan` with a recursive trigger SELECT execution plan and reuses the lane-local TestRunner.

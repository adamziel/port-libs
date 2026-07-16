# real-upstream-corpus-upsert-returning-dynamic-20260531T001351Z-0

Status: focused real-upstream corpus growth for UPSERT trigger old-row behavior with a deterministic RETURNING stream.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - Ported `upsert1-1300`: duplicate source rows inserted through UPSERT must pass the current target row as `old.*` to the `BEFORE UPDATE` trigger. The upstream regression guard aborts if `old.y` does not equal the post-update duplicate `new.y`.

Implementation:

- Added `SQLiteUpsertTriggerOldValuePlan`, a generic row-array UPSERT model for duplicate source rows that records insert/update `RETURNING` order, trigger old/new images, final rows, and change count.
- Added `SQLiteRealUpstreamUpsertReturningTriggerOldValueDynamicTest.php` with 1006 focused TestRunner cases, including 1000 seeded duplicate-source variants derived from the upstream `upsert1-1300` shape.

Non-overlap:

- This does not repeat previous `upsert4`, `upsert5`, no-target row-stream, autoincrement, conflict-target, redundant-conflict, correlated RETURNING, or trigger-recursive UPSERT slices.
- This slice owns the upstream `upsert1-1300` trigger old-row regression behavior and layers generic RETURNING stream assertions over the same duplicate-source UPSERT flow.

Dependency closure:

- No new support component is needed. The patch reuses lane-local row-array UPSERT semantics and adds a bounded generic trigger old/new image model.

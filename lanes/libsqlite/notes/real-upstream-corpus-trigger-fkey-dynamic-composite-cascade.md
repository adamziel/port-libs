## real-upstream-corpus-trigger-fkey-dynamic-composite-cascade

- Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`.
- Ported section: `fkey2-12.3.1` through `fkey2-12.3.5`.
- Behavior covered: composite parent key column order, `ON UPDATE CASCADE` from parent columns `(c34,c35)` into child columns `(c39,c38)`, and post-cascade `ON DELETE RESTRICT` behavior over the old key.
- Focused PHP evidence: `SQLiteRealUpstreamTriggerFkeyDynamicCompositeCascadeTest.php` adds 2,424 passing assertions from 100 dynamic row sets plus upstream-source citation checks.
- Non-overlap: this extends the existing trigger/FK dynamic corpus beyond the accepted `fkey2-12.2` nocase delete-trigger repair coverage and does not add runner metadata rows or fabricated upstream script ids.
- Dependency closure: no new support component is needed; the existing `SQLiteDynamicTriggerForeignKeyPlan` trigger/FK dynamic helper is reused and extended.

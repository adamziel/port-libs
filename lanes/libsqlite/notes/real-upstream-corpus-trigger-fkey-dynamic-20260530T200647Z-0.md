Real upstream corpus trigger/FK dynamic slice, 2026-05-30

- Added `SQLiteRealUpstreamTriggerFkeyCompositeDynamicTest.php`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`.
- Ported section: `fkey2-12.3.1` through `fkey2-12.3.5`, covering composite parent key column order, child key order reversal (`FOREIGN KEY(c39, c38)`), `ON UPDATE CASCADE`, failed unmatched child insert, and `ON DELETE RESTRICT` after cascade.
- Focused assertion/PASS growth: 2,094 TestRunner PASS lines in the new focused file.
- Non-overlap: this extends the existing trigger/FK dynamic corpus beyond the already-covered fkey2 nocase repair, fkey8 action journal, fkey5 foreign_key_check, trigger2 selective/cascade/conflict, and broad current/next recursive trigger files by targeting the composite fkey2-12.3 column-order cascade/restrict section.
- Dependency closure: no new support component is needed; the test reuses the existing generic `SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeRestrictCycle()` behavior.

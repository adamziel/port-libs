# Real Upstream Corpus Trigger/FK Dynamic

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T060207Z-0`

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Upstream source files used from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `fkey2.test`: `fkey2-2.*`, `fkey2-9.*`, `fkey2-11.*`, and `fkey2-12.*` deferred/action behavior.
- `fkey6.test`: deferred pragma does not defer `RESTRICT`.
- `trigger1.test`: `trigger1-6.*` `WHEN` filtering and `trigger1-9.*` before-trigger row image behavior.
- `trigger2.test`: `trigger2-2.*`, `trigger2-3.*`, and `trigger2-4.*` before/after trigger timing around row changes.
- `triggerG.test`: `triggerG-100` recursive trigger behavior.

Behavior added:

- Extended `SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php` with before-trigger FK repair before `RESTRICT`, after-trigger repair of `NO ACTION`, before-trigger `NEW` image mutation, valid and orphan child insertion from after triggers, delete-side repair, and false `WHEN` trigger suppression.
- Focused test movement in this file: `600` assertions before the slice, `692` assertions after the slice, net `+92` assertions. PASS lines increased from `42` to `51`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php` -> `1 test files, 692 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses existing native PHP trigger/FK row-array behavior and the hydrated upstream SQLite test corpus as source truth.

Non-overlap:

- This does not add metadata-only admission rows, generated fake upstream ids, WordPress-shaped APIs, numeric production suffixes, or new compatibility wrappers.
- The added cases are trigger/FK timing and dynamic child-row behavior, distinct from already-present cascade/set-null/set-default matrix rows and recursive trigger savepoint rows in the same focused file.

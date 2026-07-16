# Real Upstream Corpus Trigger/FK Dynamic

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T171116Z-0`

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
  - `triggerC-2.1`: recursive trigger programs and recursive trigger enable/disable behavior.
  - `triggerC-3`: recursion depth limit failure behavior.
  - `triggerC-6`: `PRAGMA recursive_triggers` state behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
  - deferred `NO ACTION` commit checks.
  - immediate `RESTRICT` timing.
  - `e_fkey-64`: recursive trigger pragma does not suppress foreign-key actions.

Implementation note:

- Added `SQLiteRealUpstreamTriggerFkeyDynamicNextCorpusTest.php` using generic
  record/child key data against the existing recursive trigger/deferred-FK
  planner. The test ports dynamic upstream behavior around recursive trigger
  expansion, recursive trigger disabled state, FK cascades independent of
  recursive trigger settings, deferred NO ACTION violations, RESTRICT timing,
  trigger depth errors, and trigger-driven child inserts.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNextCorpusTest.php`
  - `1 test files, 143 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNextCorpusTest.php`
  - no syntax errors
- Domain-specific API guard path from the current generic test suite
  - not run; guard file is absent in this worktree
- `git diff --check -- lanes/libsqlite`
  - passed

Dashboard delta:

- Expected focused PASS-line growth: `+143` if accepted as a new selected
  TestRunner file.
- Mapped upstream denominator remains unchanged; this ports behavior into PHP
  focused coverage without claiming new manifest rows.

Dependency closure:

- No new support component is needed. The slice reuses existing native
  recursive trigger/deferred FK behavior and adds upstream-backed focused
  coverage only.

# Real Upstream Corpus: Trigger/FK Dynamic Simple Enforcement

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T043130Z-0`

Base accepted HEAD: `7db59d242cf2590641e3217c1b87d71727256c92`

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`

Owned upstream sections:

- `fkey2-1.1.*` simple immediate foreign-key statement enforcement.
- `fkey2-1.3.*` `PRAGMA count_changes` statement results while preserving FK enforcement.
- `fkey2-1.5.*` integer primary-key child storage affinity.
- `fkey2-1.6.*` regular integer-affinity parent coercion.
- `fkey2-1.7.*` parent-key collation precedence.

Added focused file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSimpleEnforcementTest.php`

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSimpleEnforcementTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSimpleEnforcementTest.php`
  - `1 test files, 504 assertions, 0 failures`

Non-overlap:

- This slice does not touch accepted trigger/FK sections already represented by the recent dynamic action, composite cascade, schema drop, self-reference, counter, view, trigger2/4/5/6/7/8/9/A/B/C/F/G, and parent-update files.
- It specifically adds real upstream `fkey2.test` simple-schema enforcement and affinity/collation checks from `fkey2-1.*`.
- No production source APIs, domain-specific names, generated fake upstream script ids, or metadata-only denominator rows were added.

Dependency closure:

- No new support component is needed. The test uses the hydrated upstream SQLite `.test` file as source truth and a bounded lane-local PHP model of simple FK statement effects for focused corpus assertions.

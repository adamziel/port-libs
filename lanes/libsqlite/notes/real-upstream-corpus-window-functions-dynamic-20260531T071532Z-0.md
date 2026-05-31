# real-upstream-corpus-window-functions-dynamic-20260531T071532Z-0

Implemented a real upstream window-function dynamic batch under
`lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsFilterDynamic20260531Test.php`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
  sections `1.1` through `1.19`, covering `GROUPS` frame boundaries and
  `EXCLUDE` variants.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test`
  sections `5.2` through `5.3`, covering `FILTER` on window aggregate frames.

Focused movement:

- Added 1,002 distinct TestRunner PASS cases.
- Added 2,002 focused assertions.
- No production source changes.
- No lane-status counter edit; the integrator should count this as focused
  PASS-line growth only after accepting the test batch on current base.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsFilterDynamic20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsFilterDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsFilterDynamic20260531Test.php`
  - `1 test files, 2002 assertions, 0 failures`
  - 1,002 PASS lines
- `git diff --check -- lanes/libsqlite`
  - passed with no output
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run; guard file is not present in this worktree

Non-overlap:

This batch does not add another standalone `window8.test` boundary matrix or
another standalone `filter1.test` aggregate summary. It combines the two real
upstream behaviors into a dynamic GROUPS/FILTER oracle that verifies peer
groups, `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, `EXCLUDE TIES`, filtered NULL
payloads, and `group_concat` separators against `SQLiteWindowFunction`.

Dependency closure:

No new support component is needed. The batch reuses the existing native PHP
`SQLiteWindowFunction::aggregateFrameBetweenValues()` implementation and a
lane-local independent oracle.

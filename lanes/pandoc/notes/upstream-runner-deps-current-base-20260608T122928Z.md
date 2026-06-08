# Pandoc upstream runner dependency audit current-base 20260608T122928Z

Slice: `pandoc-upstream-runner-deps-current-base-20260608T122928Z`
Accepted base: `03d7c4f1ec7ff6e233514aae2d1542db24fa965c`

## Delta

- Added one native PHP audit case to `UpstreamRunnerDependencyAuditTest.php`: `benchmark:benchmark-pandoc` must remain `buildable: true` before the lane reports a non-mutating Cabal solver/build plan as ready.
- This is a bounded Cabal/upstream-runner dependency-closure check. It does not execute Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark executables, external converters, online services, live provider tests, or live-service provider tests.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `phpPass` moves `1641 -> 1642`; mapped denominator moves `2061 -> 2062`.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1638 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1655 assertions, 0 failures`.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed.
- Lane JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `UpstreamRunnerDependencyAudit` and the existing lane-local TestRunner fixture helpers. There is no WordPress example smoke for this audit-only slice because the behavior is an upstream runner dependency gate, not a user-visible conversion path.

## Follow-Up

A future upstream-runner slice can audit another non-overlapping Cabal/project closure edge, or leave a note-only audit if no safe local PHP test can move the closure. Full upstream runner parity still requires a hydrated checkout plus non-mutating Cabal planning evidence before any Haskell runner execution is considered.

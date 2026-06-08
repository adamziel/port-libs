# Pandoc Upstream Runner Dependency Audit 2026-06-08

Scope: `pandoc-upstream-runner-deps-current-base-20260608T094724Z` on accepted base `e8dffc9f0d3aa735a6dd8abc60956f05dbfe08da`.

No `port-pandoc-*.needs-lane-rework.md` rework note existed for this lane when checked.

## Change

The static upstream runner dependency audit now preserves all unconditional `source-repository-package` fields from `cabal.project` and blocks unexpected fields on already expected pinned repositories before marking a non-mutating Cabal plan as ready.

Allowed source-repository fields remain `type`, `location`, and `tag`. Extra fields such as `branch`, `subdir`, and `post-checkout-command` are reported under `projectSourceRepositoryClosure.unexpectedFields`, included in blocked reasons, and included in the activation gate.

This is additive to earlier runner-dependency slices for Cabal package types, dependency names, executable options, source directories, mixins, build tools, options, extensions, CPP/native fields, extra files, conditionals, source/artifact semantics, and source-repository type/location/tag pin closure. This slice owns extra `source-repository-package` field drift.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1398 assertions, 0 failures`.
- Red-first probe after fixture expectations failed as expected with `1 test files, 1267 assertions, 2 failures` because `projectSourceRepositoryClosure.unexpectedFields` was absent and readiness still stayed true.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1411 assertions, 0 failures`.
- Focused movement: `+1` PHP PASS case and `+13` focused assertions.

PHP lint, lane JSON validation, and `git diff --check -- lanes/pandoc` are required for this handoff and are recorded in the final response. Root harness was not run because this is an isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing static Cabal parser and `UpstreamRunnerDependencyAudit` closure model.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout and a reviewed non-mutating Cabal plan. This slice did not execute Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark executables, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Follow-Up

Either hydrate the pinned checkout only for a reviewed non-mutating plan gate, or continue with a non-overlapping static Cabal closure audit such as conditional source-repository-package handling if a future upstream project file introduces it.

# Pandoc Upstream Runner Dependency Audit 2026-06-08

Scope: `pandoc-upstream-runner-deps-current-base-20260608T102551Z` on accepted base `316968fe851e07341d518253a84225941939f5fc`.

No `port-pandoc-*.needs-lane-rework.md` rework note existed for this lane when checked.

## Change

The static upstream runner dependency audit now records exact Cabal `common` import closure for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

The expected closure is `common-executable` plus its transitive `common-options` import for the main runner and benchmark, and `test-options` for the Lua engine runner. Missing, unexpected, or unresolved common imports now block `readyForNonMutatingCabalPlan`, appear in `blockedReasons`, and are included in the activation gate and non-mutating plan requirements.

This closes a static dependency-planning gap where a hydrated checkout could add a new shared Cabal stanza or drop an expected shared import while keeping direct runner fields apparently valid.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline focused run before this slice: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1411 assertions, 0 failures`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1448 assertions, 0 failures`.
- Focused movement: `+1` PHP PASS case and `+37` focused assertions.

PHP lint, lane JSON validation, and `git diff --check -- lanes/pandoc` are required for this handoff and are recorded in the final response. Root harness was not run because this is an isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing static Cabal stanza parser and `UpstreamRunnerDependencyAudit` closure model.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout and a reviewed non-mutating Cabal plan. This slice did not execute Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark executables, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This is additive to previous upstream-runner dependency-audit slices for Cabal package identity, tested-with versions, source-repository pins and fields, project packages/flags/constraints, direct dependencies, dependency constraints, executable options, source directories, mixins, build tools, test/benchmark options, extensions, CPP/native fields, autogen/reexported/other modules, extra files, conditional branches, source/artifact semantics, and empty artifact checks.

This slice owns only Cabal `common` import closure drift and does not change native conversion behavior.

## Follow-Up

Either hydrate the pinned checkout only for a reviewed non-mutating plan gate, or continue with a non-overlapping static Cabal closure audit if another unmodeled runner-planning field is identified. Do not execute Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark executables, external converters, online services, live provider tests, or live-service provider tests from this lane.

# Upstream Runner Deps Current Base - Library Default Language

Slice: `pandoc-upstream-runner-deps-current-base-20260608T204847Z`
Base: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Scope

This slice extends the static upstream-runner dependency audit for
`pandoc-lua-engine/pandoc-lua-engine.cabal`. The audit now treats the library
stanza's `default-language` as part of the native Cabal closure and blocks
non-mutating Cabal-plan readiness when it drifts from `Haskell2010`.

This is intentionally bounded support-library work. It does not execute
Pandoc, Cabal solver/build/test commands, GHC/Haskell runners, Stack,
benchmark executables, external converters, online services, live provider
tests, or live-service provider tests.

## Evidence

- Baseline focused run before the implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1887 assertions, 0 failures`.
- Red-first check after adding the fixture but before the gate:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed with `1 test files, 1888 assertions, 1 failures`; the fixture changed
  only the Lua engine library stanza to `default-language: Haskell98`, but the
  old audit still marked the non-mutating Cabal plan ready.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1912 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses
`UpstreamRunnerDependencyAudit` Cabal stanza parsing and closure gating. It adds
one mapped native audit case and moves the lane-local `phpPass` from `1834` to
`1835`.

Follow-up remains a reviewed hydrated checkout plus non-mutating Cabal plan
evidence before any upstream Haskell runner or benchmark execution.

## Non-Overlap

This does not repeat the prior upstream-runner audit slices for runner and
benchmark `default-language`, `other-extensions`, `autogen-modules`,
`test-options`, `data-files`, `extra-doc-files`, Lua engine library
dependencies, extension drift, file globs, native/system fields, or conditional
branches. The new coverage is only the `pandoc-lua-engine` library stanza
`default-language` field.

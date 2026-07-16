# Upstream Runner Dependency Audit - Cabal Flag Fields

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T043923Z`
Accepted base: `751070fca2ca1c3ef7b50b0753a60f0f2fcd712e`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.

## Source Truth

The lane manifest pins upstream Pandoc at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. This slice stays inside the existing native PHP upstream-runner dependency audit and its lane-local Cabal fixtures. It does not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now records expected Cabal package flag `default` and `manual` fields, not just the presence of flag names referenced by `cabal.project`.

Covered package flag fields:

- `pandoc.cabal`: `embed_data_files` default `False` and manual `True`; `http` default `True` and manual `True`.
- `pandoc-cli/pandoc-cli.cabal`: `lua` default `True`; `nightly` default `False`; `repl` default `True`; `server` default `True`, with absent `manual` values recorded as part of the expected closure.

The audit now blocks the non-mutating Cabal planning gate when those fields drift, with explicit `mismatchedFlagFields` provenance and blocked-reason text. The new negative fixture changes `embed_data_files`, `lua`, and `nightly` field values to prove the gate catches both wrong booleans and unexpected/manual field presence before any solver or runner step.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2474 assertions, 0 failures
```

Delta versus the prior upstream-runner audit note baseline (`2445` assertions):

- `+1` focused PHP PASS case.
- `+29` focused assertions.
- `+1` mapped upstream-runner dependency case in `UPSTREAM_TEST_MANIFEST.json`.

Syntax checks run for changed PHP files:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php

php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Example smoke: not run, because this is an upstream-runner dependency audit slice and no example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP Cabal stanza parsing/audit code and lane-local fixtures. Full upstream runner parity remains gated on a hydrated pinned upstream checkout plus an explicitly authorized non-mutating Cabal plan/Haskell runner evidence pass.

## Non-Overlap

This slice does not repeat the prior upstream-runner Cabal plan-stability audit, package identity/setup checks, package flag name presence checks, project pin/package/constraint checks, runner and benchmark dependency checks, runner option/module/artifact checks, or Lua/server/CLI closure audits. It owns only Cabal package flag `default`/`manual` field closure needed before non-mutating upstream runner dependency planning.

## Follow-Up

Hydrate or verify the pinned upstream checkout and run the same native static audit against real `pandoc.cabal`, `pandoc-cli`, `pandoc-lua-engine`, `pandoc-server`, `cabal.project`, and `cabal.project.freeze` sources before any explicitly authorized non-mutating Cabal plan.

# Upstream Runner Dependency Audit - Package Source Repositories

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T050538Z`
Accepted base: `5d02a10932dbbd350c989c1902aead80ac5c366a`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.

## Source Truth

The lane manifest pins upstream Pandoc at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Targeted static reads of the pinned upstream Cabal files showed `source-repository head` with `type: git` and `location: https://github.com/jgm/pandoc.git` in:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `pandoc-server/pandoc-server.cabal`
- `pandoc-cli/pandoc-cli.cabal`

This slice stays inside the native PHP upstream-runner dependency audit. It does not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now records exact package-level Cabal `source-repository head` closure for the four Pandoc package manifests before any non-mutating Cabal plan is allowed.

The audit blocks when a package manifest:

- omits the required `head` stanza;
- changes `type` away from `git`;
- changes `location` away from `https://github.com/jgm/pandoc.git`;
- adds unexpected package-level repository kinds;
- adds extra fields such as `branch` or `subdir` to the expected `head` stanza.

The positive fixture now carries matching package-level source-repository stanzas, and the negative fixture proves missing, mismatched, unexpected, and extra-field repository drift all keep `readyForNonMutatingCabalPlan` false.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2506 assertions, 0 failures
```

Adjacent upstream-runner dependency verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2603 assertions, 0 failures
```

Delta versus the prior upstream-runner audit note baseline (`2474` assertions):

- `+1` focused PHP PASS case.
- `+32` focused assertions.
- `+1` mapped upstream-runner dependency case in `UPSTREAM_TEST_MANIFEST.json`.

Syntax checks run for changed PHP files:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php

php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Whitespace check:

```text
git diff --check -- lanes/pandoc
passed with no output
```

Example smoke: not run, because this is an upstream-runner dependency audit slice and no example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP Cabal stanza parsing/audit code and lane-local fixtures. Full upstream runner parity remains gated on a hydrated pinned upstream checkout plus an explicitly authorized non-mutating Cabal plan/Haskell runner evidence pass.

## Non-Overlap

This slice does not repeat the prior upstream-runner Cabal plan-stability audit, package identity/setup checks, package flag name/field checks, package data/extra/native-system file checks, project pin/package/constraint checks, runner and benchmark dependency checks, runner option/module/artifact checks, or Lua/server/CLI closure audits. It owns only package-level Cabal `source-repository head` closure needed before non-mutating upstream runner dependency planning.

## Follow-Up

Hydrate or verify the pinned upstream checkout and run the same native static audit against real `pandoc.cabal`, `pandoc-cli`, `pandoc-lua-engine`, `pandoc-server`, `cabal.project`, and `cabal.project.freeze` sources before any explicitly authorized non-mutating Cabal plan.

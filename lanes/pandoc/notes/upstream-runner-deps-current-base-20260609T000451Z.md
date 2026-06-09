# Upstream Runner Dependency Audit - Package Extra Tmp Files

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T000451Z`
- Accepted base: `48a59c8d15f1cb4b103c2c2657a62cb105c4a87a`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats package-level Cabal
`extra-tmp-files` as part of the closure required before a non-mutating Cabal
plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the audited workspace package
files do not require package-level `extra-tmp-files`:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `pandoc-server/pandoc-server.cabal`
- `pandoc-cli/pandoc-cli.cabal`

The native audit now parses top-level `extra-tmp-files` before component
stanzas, reports expected and present package temp-file glob closure, and
blocks readiness when generated temporary runner/package globs appear at
package scope.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2196 assertions, 0 failures`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2215 assertions, 0 failures`.
- Syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` and
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` both
  reported no syntax errors.
- JSON validation:
  `lanes/pandoc/lane-status.json OK` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` completed with no output.
- Example smoke:
  not run; this audit-only slice has no WordPress-visible conversion path.
- Root harness:
  not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, the native Cabal top-level file-glob parser,
and lane-local TestRunner fixtures.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
custom setup hooks, package flags, package-level `data-files`, package-level
`extra-doc-files`, package-level `extra-source-files`, project packages,
project flags, project constraints, source-repository pins/fields,
tested-with matrices, runner/benchmark types, direct dependencies, common
imports, source directories, executable options, mixins, build tools,
test/benchmark options, extensions, CPP/native fields, generated modules,
module-interface fields, runner/benchmark component file globs, conditional
branches, Lua-engine library dependencies, Lua-engine source artifacts, or
Lua-engine library file/native/system fields.

The owned behavior is only package-level Cabal `extra-tmp-files` closure
before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could
cover package-level native/system fields or the `pandoc-cli`/`pandoc-server`
component dependency closure without running Pandoc, Cabal build/test
commands, Haskell runners, external converters, online services, live provider
tests, or live-service provider tests.

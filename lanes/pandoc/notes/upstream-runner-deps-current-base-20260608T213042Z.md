# Upstream Runner Dependency Audit - Lua Library Source Closure

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T213042Z`
- Accepted base: `9fca7a8f155d1a30d46db28e808e4b225a69a919`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats the pinned
`pandoc-lua-engine` library `hs-source-dirs`, `other-modules`, and matching
library source-file artifacts as part of the closure required before any
non-mutating Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the library source directory is
`src`, the exposed module is `Text.Pandoc.Lua`, and the bounded audit records
the exact library `other-modules` list plus SHA-256/byte provenance for every
expected library source artifact under `pandoc-lua-engine/src`. Missing,
unexpected, wrong-type, or empty library source artifacts now block readiness
before any solver/build command can be considered.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, Word/LibreOffice, zip/unzip, external converter,
online service, live provider test, or live-service provider test was executed.

## Evidence

- Prior comparable focused audit evidence from the previous current-base
  upstream-runner slice: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1940 assertions, 0 failures`.
- Red-first after adding the new source-closure expectations:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed with `1 test files, 29 assertions, 71 failures` because the audit did
  not yet expose `expectedLuaEngineLibraryOtherModules()`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2070 assertions, 0 failures`.
- Syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` and
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` both
  reported no syntax errors.
- Status JSON:
  `php -r '...'` validation for `lanes/pandoc/lane-status.json` reported
  `JSON OK`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` completed with no output.

## Dependency Closure

No new support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the lane-local TestRunner fixture helpers, the
existing static Cabal stanza parser, and native filesystem hashing/type checks.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
custom setup hooks, package flags, project packages/flags/constraints,
source-repository pins/fields, tested-with matrices, test-suite and benchmark
types/buildable state, direct runner/benchmark dependencies, common imports,
source directories, executable options, build tools, test/benchmark options,
runner/benchmark extensions, CPP/native fields, autogen/reexported/other
modules, extra file globs, runner/benchmark conditional branches, Lua-engine
library dependencies, library exposed modules, library default-language,
library extension drift, library native/system fields, or library file
artifact globs.

The owned behavior is only `pandoc-lua-engine` library source closure:
`hs-source-dirs`, `other-modules`, and source artifact provenance.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could
cover another Cabal library component field, or retire this runner-dependency
audit family until a hydrated upstream checkout is available.

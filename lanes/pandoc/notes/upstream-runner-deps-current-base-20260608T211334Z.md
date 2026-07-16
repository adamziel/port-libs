# Upstream Runner Dependency Audit - Lua Library Exposed Modules

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T211334Z`
- Accepted base: `860604a0752757d495f65dc774700e48fce8b337`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present; only stale `20260525` notes were found under the handoff-candidate stale directory.

## Behavior

The static upstream-runner dependency audit now treats the pinned
`pandoc-lua-engine` library `exposed-modules` field as part of the Cabal
closure required before any non-mutating Cabal plan can be marked ready.

At the pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the library exposes
`Text.Pandoc.Lua`. This slice parses that field through the native Cabal stanza
parser, reports expected/present/missing/unexpected exposed modules, and blocks
runner readiness when the exported module surface is missing or drifted.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1912 assertions, 0 failures`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1940 assertions, 0 failures`.

## Dependency Closure

No new native conversion support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the lane-local TestRunner fixture helpers, and
the existing static Cabal metadata parser.

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
library dependencies, library default-language, library extension drift,
library native/system fields, or library file artifact globs.

The new owned behavior is only `pandoc-lua-engine` library `exposed-modules`
closure before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could cover
remaining library module-shape fields such as library `other-modules` source
artifact provenance.

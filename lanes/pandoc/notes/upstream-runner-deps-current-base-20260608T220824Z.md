# Upstream Runner Dependency Audit - Lua Library Mixins and Build Tools

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T220824Z`
- Accepted base: `5ca5ed5c01549ddcb5727c8343ae1666cecfe98d`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats the pinned
`pandoc-lua-engine` library `mixins`, `build-tool-depends`, and legacy
`build-tools` fields as part of the Cabal closure required before any
non-mutating Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the `pandoc-lua-engine` library
does not require Cabal mixins or build-tool dependencies. The native audit now
parses those fields from imported common stanzas and the library stanza,
records raw present values, normalizes build-tool diagnostics with the same
`build-tool-depends:` / `build-tools:` labels used by runner and benchmark
components, and blocks readiness if either surface drifts.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Prior focused upstream-runner audit baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2070 assertions, 0 failures`.
- Red-first after adding the Lua library mixin/build-tool case:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed with `1 test files, 2071 assertions, 1 failures` because the mutated
  Lua library fixture still returned `readyForNonMutatingCabalPlan=true`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2090 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the existing native Cabal stanza parser, and
the lane-local TestRunner fixture helpers.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
custom setup hooks, package flags, project packages/flags/constraints,
source-repository pins/fields, tested-with matrices, test-suite and benchmark
types/buildable state, direct runner/benchmark dependencies, common imports,
source directories, executable options, runner/benchmark build tools,
test/benchmark options, runner/benchmark extensions, CPP/native fields,
autogen/reexported/module-interface fields, file globs, runner/benchmark
conditional branches, Lua-engine library dependencies, exposed modules,
source directories, other modules, source artifacts, default-language,
extension drift, file artifact globs, native/system fields, or conditional
branches.

The owned behavior is only `pandoc-lua-engine` library mixin/build-tool
closure before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could
cover another library component field such as autogen, reexported, or module
interface drift, or pause this audit family until a hydrated upstream checkout
is available.

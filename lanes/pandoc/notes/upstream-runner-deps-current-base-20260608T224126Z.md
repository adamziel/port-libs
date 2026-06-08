# Upstream Runner Dependency Audit - Lua Library Generated Module Fields

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T224126Z`
- Accepted base: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats the pinned
`pandoc-lua-engine` library `autogen-modules`, `reexported-modules`,
`signatures`, and `virtual-modules` fields as part of the Cabal closure that
must be clean before any non-mutating Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the `pandoc-lua-engine` library
does not require generated modules, reexports, signatures, or virtual modules.
The native audit now parses those fields through imported common stanzas,
records present values in `luaEngineLibraryClosure`, and blocks readiness when
the library introduces unexpected generated/module-interface surfaces.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed with `1 test files, 2112 assertions, 1 failures` because the mutated
  Lua library fixture with generated/module-interface fields still returned
  `readyForNonMutatingCabalPlan=true`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2142 assertions, 0 failures`.

Additional verification commands are recorded in the final handoff output.

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
source-repository pins/fields, tested-with matrices, runner/benchmark types,
direct dependencies, common imports, source directories, executable options,
runner/benchmark mixins, build tools, test/benchmark options, extensions,
CPP/native fields, autogen/reexported/module-interface fields on runner and
benchmark stanzas, runner/benchmark file globs, runner/benchmark conditional
branches, Lua-engine library dependencies, exposed modules, source
directories, other modules, source artifacts, default-language,
extension drift, file artifact globs, native/system fields, conditional
branches, or Lua library mixin/build-tool closure.

The owned behavior is only `pandoc-lua-engine` library generated/module
interface field closure before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. If a hydrated checkout becomes available,
run this native static audit against real package/project files before any
Cabal solver/build command.

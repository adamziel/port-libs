# Upstream Runner Dependency Audit - Package Native/System Fields

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T002647Z`
- Accepted base: `28428232606f6fb6b3df81661dee1f40b90244b3`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats package-level Cabal
native/system fields as part of the closure required before a non-mutating
Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the audited workspace package
files do not require package-level native/system dependency fields:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `pandoc-server/pandoc-server.cabal`
- `pandoc-cli/pandoc-cli.cabal`

The native audit now parses top-level Cabal native/system fields before any
`common`, `library`, `test-suite`, `benchmark`, `executable`, `flag`, or
`source-repository` stanza boundary, reports expected/present package closure,
and blocks readiness when package scope adds native C/C++/JS sources, native
libraries, framework paths, include paths, pkg-config dependencies, linker
flags, or preprocessor/compiler helper options.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2215 assertions, 0 failures`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2238 assertions, 0 failures`.
- Focused delta from current accepted upstream-runner audit baseline:
  `+1` PASS case and `+23` assertions.
- Example smoke:
  not run; this audit-only slice has no WordPress-visible conversion path.
- Root harness:
  not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, the native Cabal top-level field parser, and
lane-local TestRunner fixtures.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
custom setup hooks, package flags, package-level `data-files`, package-level
`extra-doc-files`, package-level `extra-source-files`, package-level
`extra-tmp-files`, project packages, project flags, project constraints,
source-repository pins/fields, tested-with matrices, runner/benchmark types,
direct dependencies, common imports, source directories, executable options,
mixins, build tools, test/benchmark options, extensions, CPP/native component
fields, generated modules, module-interface fields, runner/benchmark component
file globs, conditional branches, Lua-engine library dependencies, Lua-engine
source artifacts, or Lua-engine library native/system fields.

The owned behavior is only package-level Cabal native/system field closure
before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could
cover `pandoc-cli` and `pandoc-server` library/executable dependency closure
without running Pandoc, Cabal build/test commands, Haskell runners, external
converters, online services, live provider tests, or live-service provider
tests.

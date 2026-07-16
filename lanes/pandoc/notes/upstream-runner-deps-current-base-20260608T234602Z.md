# Upstream Runner Dependency Audit - Package Extra File Globs

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T234602Z`
- Accepted base: `188120ad12d64170af6df8437e20fe1b6e719eb9`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats package-level Cabal
`extra-doc-files` and `extra-source-files` as part of the closure required
before a non-mutating Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, package-level extra files are:

- `pandoc.cabal`: `extra-doc-files` for `changelog.md`, `AUTHORS.md`,
  `INSTALL.md`, `README.md`, `CONTRIBUTING.md`, and `BUGS`, plus the large
  `extra-source-files` fixture/test glob closure.
- `pandoc-lua-engine/pandoc-lua-engine.cabal`: package-level
  `extra-source-files` for Lua engine README and test fixture payloads.
- `pandoc-cli/pandoc-cli.cabal`: package-level `extra-source-files` for the
  three generated manpage sources.
- `pandoc-server/pandoc-server.cabal`: no package-level extra file globs.

The native audit parses these top-level fields before component stanzas,
normalizes and deduplicates globs consistently with existing component file
glob parsing, reports expected/present/missing/unexpected package extra file
closures, and blocks readiness when documentation or source fixture globs
drift.

Source truth was checked with targeted raw reads of the pinned upstream Cabal
files. No Pandoc executable, Cabal solver/build/test command, Haskell runner,
Stack command, benchmark executable, external converter, online service, live
provider test, or live-service provider test was executed.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  initially failed with `1 test files, 2189 assertions, 1 failures` because an
  older package `data-files` drift fixture used an insertion anchor that moved
  after the Lua engine package `extra-source-files` fixture was added.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2196 assertions, 0 failures`.
- Syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` and
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` both
  reported no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses the native
`UpstreamRunnerDependencyAudit` Cabal parser/list normalizer and lane-local
TestRunner fixture helpers.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
custom setup hooks, package flags, package-level `data-files`, project
packages/flags/constraints, source-repository pins/fields, tested-with
matrices, runner/benchmark types, direct dependencies, common imports, source
directories, executable options, runner/benchmark mixins, build tools,
test/benchmark options, extensions, CPP/native fields,
autogen/reexported/module-interface fields, component file globs,
runner/benchmark conditional branches, Lua-engine library dependencies,
exposed modules, source directories, other modules, source artifacts,
default-language, extension drift, file artifact globs, native/system fields,
conditional branches, mixins/build tools, or generated/module-interface fields.

The owned behavior is only package-level `extra-doc-files` and
`extra-source-files` closure before Cabal planning.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. If a hydrated checkout becomes available,
run this native static audit against the real package/project files before any
Cabal solver/build command.

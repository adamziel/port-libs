# Upstream Runner Dependency Audit - Workspace Package Cabal Closure

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T215726Z`
- Accepted base: `d291953d10cb3a81d9c31878d6d7b3226cc33af0`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.

## Behavior

The static upstream-runner dependency audit now treats the pinned workspace
package Cabal files for `pandoc-server` and `pandoc-cli` as required inputs
before any non-mutating Cabal plan can be marked ready.

At pinned upstream Pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, `cabal.project` lists
`pandoc-server` and `pandoc-cli` as workspace packages. The bounded audit now
requires `pandoc-server/pandoc-server.cabal` and
`pandoc-cli/pandoc-cli.cabal`, verifies their package identity/version headers,
and checks that `pandoc-cli` still defines the Cabal flags used by the project
closure: `lua`, `nightly`, `repl`, and `server`.

The local hydrated upstream cache for Pandoc was absent in this isolated
worktree, so source truth was read from the pinned upstream public repository:

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/cabal.project`
- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/pandoc-server/pandoc-server.cabal`
- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/pandoc-cli/pandoc-cli.cabal`

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, Word/LibreOffice, zip/unzip, external converter,
online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline before the patch:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2070 assertions, 0 failures`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 2091 assertions, 0 failures`.
- Syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` and
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` both
  reported no syntax errors.
- Status JSON:
  `php -r '...'` validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reported `JSON OK`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` completed with no output.

## Dependency Closure

No new support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the lane-local TestRunner fixture helpers, the
existing static Cabal stanza parser, and package flag-definition checks.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity
over `pandoc.cabal`/`pandoc-lua-engine`, custom setup hooks, package flags,
project packages/flags/constraints, source-repository pins/fields,
tested-with matrices, test-suite and benchmark types/buildable state, direct
runner/benchmark dependencies, common imports, source directories, executable
options, build tools, test/benchmark options, runner/benchmark extensions,
CPP/native fields, autogen/reexported/other modules, extra file globs,
runner/benchmark conditional branches, Lua-engine library dependencies,
library exposed modules, library default-language, library extension drift,
library native/system fields, or library file artifact globs.

The owned behavior is only workspace package Cabal-file closure for
`pandoc-server` and `pandoc-cli`: required package files, package
identity/version headers, and `pandoc-cli` flag definitions.

## Follow-Up

Keep upstream-runner work static unless the supervisor explicitly authorizes a
hydrated checkout and Cabal plan. A next non-overlapping audit slice could
cover bounded `pandoc-cli` executable dependency/source closure or
`pandoc-server` library dependency/source artifact closure.

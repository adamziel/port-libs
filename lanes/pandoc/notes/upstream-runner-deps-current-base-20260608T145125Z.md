# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T145125Z`

Accepted base: `809a2da5c6b7ac8981b6fadaa6d9b301c311a0e2`

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell runner, Stack command, benchmark executable, external converter, online
service, live provider test, or live-service provider test was executed.

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Audit Delta

`UpstreamRunnerDependencyAudit` now records conditional branches from the
default `pandoc-lua-engine` Cabal `library` stanza.

The static audit allows the known optional upstream branch:

- `library default: if flag(repl)`

That branch is already documented in earlier lane source-truth notes as the
optional `hslua-repl` closure, and it is not required for the default runner
dependency plan.

The audit now blocks unexpected Lua-engine library conditionals such as:

- `library default: if os(windows)`
- `library default: else`

This closes a dependency-planning gap where a hydrated checkout could add
platform-specific or fallback Lua-engine library branches without changing the
runner or benchmark stanzas and still be marked ready for a non-mutating Cabal
plan.

## Dependency Closure

No new native PHP conversion support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the lane-local TestRunner fixture helpers, and
the existing Cabal stanza parser.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and recording a
reviewed non-mutating Cabal plan. This slice does not build or run upstream
Haskell test executables.

## Non-Overlap

This slice avoids prior upstream-runner audit rows for package identity,
custom setup hooks, package flags, project packages, project constraints,
source-repository fields, tested-with matrices, test-suite and benchmark
types/buildable state, direct runner/benchmark dependencies, common imports,
source directories, executable options, build tools, test/benchmark options,
extensions, CPP/native fields, autogen/reexported/other modules, extra
source/doc/tmp/data files, runner/benchmark conditional branches, empty
artifacts, artifact hashes, entry-source semantics, and unexpected
`pandoc-lua-engine` Lua-support `build-depends`.

The new owned behavior is only conditional-branch drift in the
`pandoc-lua-engine` library stanza before Cabal planning.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1677 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed with `1 test files, 1678 assertions, 1 failures` because the
  unexpected library branch fixture stayed `readyForNonMutatingCabalPlan: true`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1700 assertions, 0 failures`.

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  reported no syntax errors.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported no syntax errors.
- Lane JSON validation for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json` reported `pandoc JSON ok`.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

Example smoke: not run - no example was added or changed.

Root harness: not run - isolated micro-slice.

## Next Task

Hydrate the pinned upstream Pandoc checkout only for static preflight, or choose
a non-overlapping Cabal/project closure gate such as library exposed-module or
default-extension drift. Keep Pandoc, Cabal solver/build/test commands, Haskell
runners, Stack, benchmark executables, external converters, online services,
live provider tests, and live-service provider tests out of this audit slice.

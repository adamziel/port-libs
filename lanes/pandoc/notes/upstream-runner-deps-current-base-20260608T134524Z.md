# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T134524Z`.

Accepted base: `bb1176a9198890aa4bba431429e05229d17ce5c7`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. It does not execute Pandoc, Cabal solver/build/test
commands, Haskell runners, Stack, benchmark executables, Word, LibreOffice,
`zip`/`unzip`, external template engines, TeX/PDF engines, browser renderers,
online services, live provider tests, or live-service provider tests.

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Static Evidence

- A bounded cache scan found no hydrated Pandoc `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, `test-pandoc.hs`, or
  `test-pandoc-lua-engine.hs` files under
  `/home/claude/port-libs/.upstream-cache`.
- `command -v ghc` found `/usr/bin/ghc`; `ghc --numeric-version` returned
  `9.10.3`.
- `command -v cabal` found `/usr/bin/cabal`; `cabal --numeric-version`
  returned `3.12.1.0`.
- `command -v stack` returned no path.

## Audit Behavior

`UpstreamRunnerDependencyAudit` now blocks non-mutating Cabal runner planning
when the `pandoc-lua-engine` library adds unexpected Lua support dependencies
to its resolved Cabal `build-depends` closure.

The existing audit already required selected `pandoc-lua-engine` library
dependencies such as `hslua-module-doclayout`, `hslua-module-zip`, `lpeg`, and
`pandoc-lua-marshal`. This slice closes the inverse gap: a hydrated checkout
can no longer add new `hslua-module-*`, `pandoc-lua-*`, or `lpeg`-family
support dependencies and still be marked ready for a Cabal plan. Ordinary
inherited package dependencies such as `base` remain outside this bounded
Lua-support closure because the current fixture does not model the exact full
library build-depends set.

The audit now preserves dependency constraints for parsed library
`build-depends` so blocked reasons include the added dependency and version
range.

## Dependency Closure

No new native PHP conversion support component is needed. This reuses the
existing `UpstreamRunnerDependencyAudit` static Cabal/project audit path and
focused fixture helpers.

Full upstream runner parity remains blocked by the missing hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and by the separate
need for a reviewed non-mutating Cabal plan. This slice only adds a bounded
static dependency gate; it does not build or run upstream Haskell test
executables.

## Non-Overlap

This slice avoids prior upstream-runner audit rows for package identity,
custom setup hooks, package flags, project packages, project constraints,
source-repository fields, tested-with matrices, test-suite types, direct
runner/benchmark dependencies, common imports, source directories, executable
options, build tools, test/benchmark options, extensions, cpp-options,
autogen/reexported modules, other-modules, extra source/doc/tmp/data files,
native/system fields, conditional branches, entry-source semantics, and
artifact provenance.

The new owned behavior is only unexpected `pandoc-lua-engine` library
Lua-support build-depends before Cabal planning.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed: 1 test files, 1655 assertions, 0 failures.
- Red-first after adding the fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  failed: 1 test files, 1656 assertions, 1 failures because
  `readyForNonMutatingCabalPlan` stayed `true` after adding unexpected
  `pandoc-lua-engine` Lua support dependencies.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed: 1 test files, 1677 assertions, 0 failures.
- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` passed.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Example smoke: not run; no example was added or updated.
- Root harness: not run - isolated micro-slice.

## Next Task

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry files, runner
artifacts, benchmark artifacts, and Lua-support dependency closure before any
Cabal solver/build command. If the static audit is ready, record a
non-mutating Cabal plan for `test:test-pandoc`, `test:test-pandoc-lua-engine`,
and `benchmark:benchmark-pandoc`; keep Haskell runner and benchmark execution
out of this dependency audit slice.

# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T114956Z`.

Accepted base: `70a475c01be3e17bfc6649b965eb95d8dec04810`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. It does not execute Pandoc, Cabal solver/build/test commands,
Haskell runners, Stack, benchmark executables, Word, LibreOffice, `zip`/`unzip`,
external template engines, TeX/PDF engines, browser renderers, online services,
live provider tests, or live-service provider tests.

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Audit Behavior

The static runner dependency audit now records file provenance for present
runner and benchmark artifacts before a non-mutating Cabal plan can become
ready.

For every expected runner source/golden fixture file and benchmark source/data
artifact file, the audit records:

- `sha256`
- `bytes`

Directory artifacts stay type-checked through the existing artifact closure and
do not receive file provenance rows. Empty files still stay in `emptyFiles`, and
missing or wrong-type artifacts still block the Cabal plan before any solver,
build, runner, or benchmark command can run.

The non-mutating plan and activation gate now explicitly require runner and
benchmark artifact hashes. The focused fixture checks both the ready path and
the per-file hash/byte values against synthetic pinned project contents.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
`UpstreamRunnerDependencyAudit` static Cabal/project audit path and fixture
helpers.

The full upstream runner remains intentionally gated on a separate hydrated
checkout and reviewed non-mutating Cabal plan. This slice only ports the
artifact-provenance contract for dependency readiness; it does not build or run
upstream Haskell test executables.

## Non-Overlap

This slice avoids prior upstream-runner audit rows for setup hooks, project
packages, source-repository fields, tested-with matrices, package flag
definitions, test-suite types, direct dependencies, common imports,
hs-source-dirs, executable options, build tools, extensions, autogen and
reexported modules, extra source/doc/tmp/data files, native/system fields, and
benchmark component closure. The new owned behavior is SHA-256/byte-count
provenance for runner and benchmark file artifacts.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed: 1 test files, 1471 assertions, 0 failures.
- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` passed.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed: 1 test files, 1638 assertions, 0 failures.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Example smoke: not run; no example was added or updated.
- Root harness: not run - isolated micro-slice.

## Next Task

Continue upstream-runner dependency closure only with static Cabal/project gates
or a separately authorized hydrated-checkout audit. Do not execute Pandoc,
Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests from this lane.

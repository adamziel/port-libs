# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T110823Z`.

Accepted base: `051e7e500f40498553caa2ec50bf81bc6341ef12`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. It does not execute Pandoc, Cabal solver/build/test commands,
Haskell runners, Stack, benchmark executables, Word, LibreOffice, `zip`/`unzip`,
external template engines, TeX/PDF engines, browser renderers, online services,
live provider tests, or live-service provider tests.

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Audit Behavior

The static runner dependency audit now records package-level Cabal `flag`
stanzas for package files whose `cabal.project` flags are part of the runner
closure.

For the pinned Pandoc runner project, `cabal.project` enables the `pandoc`
package flags `+embed_data_files` and `+http`. The audit now requires those
flags to be declared by `pandoc.cabal` before
`readyForNonMutatingCabalPlan` can become true. If either definition is absent,
the audit reports:

- `packageFlagDefinitionClosure.missingFlags`
- a blocked reason beginning `missing Cabal package flag definitions`
- an activation gate that still calls out package flag definitions for
  `cabal.project` flags

The hydrated fixture path records the expected definitions as present and keeps
the non-mutating plan available. The red-path fixture removes both flag stanzas
from `pandoc.cabal` while leaving `cabal.project` unchanged, proving that
project-level flag closure alone is not enough to mark the runner plan ready.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
`UpstreamRunnerDependencyAudit` Cabal/project static audit path and adds one
bounded prerequisite gate.

The full upstream runner remains intentionally gated on a separate hydrated
checkout and reviewed non-mutating Cabal plan. This slice only ports the format
contract for dependency readiness; it does not build or run upstream Haskell
test executables.

## Non-Overlap

This slice avoids prior upstream-runner audit rows for test-suite type,
other/default extensions, autogen and reexported modules, extra source/doc/tmp
files, data files, executable options, native/system/preprocessor fields,
common imports, custom setup hooks, project packages, project constraints, and
source-repository package closure. The new owned behavior is package flag
definition closure for `cabal.project` flags.

## Verification

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` passed.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed: 1 test file, 1471 assertions, 0 failures.
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

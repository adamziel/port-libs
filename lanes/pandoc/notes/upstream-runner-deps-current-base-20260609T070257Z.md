# Upstream Runner Dependency Audit - Package Flag Closure

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T070257Z`
Accepted base: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane
before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

This slice used the lane-local PHP Cabal fixture corpus in
`UpstreamRunnerDependencyAuditTest.php` and did not hydrate or mutate an
upstream checkout. It did not execute Pandoc, Cabal solver/build/test commands,
Haskell test binaries, benchmark executables, Stack, Word, LibreOffice,
zip/unzip, external template engines, external converters, TeX/PDF engines,
browser renderers, online services, live provider tests, or live-service
provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now records unexpected top-level Cabal `flag`
stanzas per package manifest and blocks `readyForNonMutatingCabalPlan` until
those generated or unreviewed flags are removed or explicitly modeled.

The coverage exercises unexpected package flags in all package manifests that
currently define expected flags:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `pandoc-cli/pandoc-cli.cabal`

The existing expected-flag and default/manual field checks are preserved. This
slice only adds the missing unexpected-flag closure so the dependency runner
audit cannot silently accept a changed solver surface.

## Evidence

Red-first focused regression run before the implementation change:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL blocks unexpected cabal package flag definitions before solver planning (lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php)
Expected: false
Actual: true
1 test files, 2582 assertions, 1 failures
```

Focused direct run after the implementation change:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2593 assertions, 0 failures
```

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2700 assertions, 0 failures
```

Delta:

- `+1` focused PHP PASS case.
- `+11` focused assertions in `UpstreamRunnerDependencyAuditTest.php`.
- `phpPass` updated from `2467` to `2468`.
- Mapped upstream inventory updated from `2851` to `2852`.

Example smoke: not run, because this is an upstream-runner dependency audit and
no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing
`UpstreamRunnerDependencyAudit` static Cabal parser and lane TestRunner
fixtures.

The current blocker remains the absence of a hydrated pinned Pandoc checkout
with reviewed dependency-plan evidence. Before claiming upstream runner
dependency closure, hydrate or verify the pinned checkout and rerun the
lane-local static audit against real `cabal.project`, `cabal.project.freeze`,
package manifests, runner entry files, benchmark entry files, and required
source/golden artifacts. Only after that audit is clean should a separate
explicitly authorized slice record a descriptor-only non-mutating Cabal plan.

## Non-Overlap

This does not repeat accepted support-library slices for ZIP/OPC, DOCX, ODT,
EPUB, archive compression, math, citations, BibTeX, XML/HTML DOM, charset,
syntax highlighting, table geometry, PDF handoff, or legacy DOC/CFB behavior.

It also does not repeat prior upstream-runner dependency checks for required
files, package identity, setup stanzas, missing or mismatched package flag
fields, cabal.project package flags, cabal.project conditionals, runner
dependencies, benchmark dependencies, non-empty cache roots, or runner
artifact hashes. The owned output is only unexpected top-level Cabal package
flag definitions before non-mutating solver-plan readiness.

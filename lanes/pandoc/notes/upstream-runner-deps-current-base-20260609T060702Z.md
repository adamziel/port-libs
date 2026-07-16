# Upstream Runner Dependency Audit - Current Base Cache Gate

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T060702Z`
Accepted base: `aea0bbc5620fdf1b622909ec6e5a23e6c3713930`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane
before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

Current local cache discovery found no Pandoc Cabal package or project files
under `/home/claude/port-libs/.upstream-cache`:

- no `cabal.project`
- no `cabal.project.freeze`
- no `pandoc.cabal`
- no `pandoc-lua-engine/pandoc-lua-engine.cabal`
- no `pandoc-server/pandoc-server.cabal`
- no `pandoc-cli/pandoc-cli.cabal`
- no `test/test-pandoc.hs`
- no `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`

Tool availability remains partial but sufficient for static gating:

- `ghc --numeric-version`: `9.10.3`
- `cabal --numeric-version`: `3.12.1.0`
- `stack`: absent

This slice does not execute Pandoc, Cabal solver/build/test commands, Haskell
test binaries, benchmark executables, Stack, Word, LibreOffice, zip/unzip,
external template engines, external converters, TeX/PDF engines, browser
renderers, online services, live provider tests, or live-service provider
tests.

## Behavior

No new PHP implementation was added because this micro-slice is explicitly an
upstream-runner dependency audit and no safe local Cabal build or solver step
is available without hydrating the pinned upstream checkout.

The existing lane-local audit still classifies the current base as:

```text
status=blocked_missing_upstream_source
hydrated=no
completeRoot=none
missing=cabal.project,pandoc.cabal,pandoc-lua-engine/pandoc-lua-engine.cabal,pandoc-server/pandoc-server.cabal,pandoc-cli/pandoc-cli.cabal,test/test-pandoc.hs,pandoc-lua-engine/test/test-pandoc-lua-engine.hs
tools=
stablePlan=
summary=Pandoc upstream runner dependency closure is blocked because no single local checkout contains the required Cabal files and Tasty test entrypoints.
```

This confirms the dependency gate remains a missing pinned upstream source
checkout, not a missing native PHP support-library component.

## Evidence

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2655 assertions, 0 failures
```

Current-base tool/cache probes:

```text
ghc --numeric-version
9.10.3

cabal --numeric-version
3.12.1.0

find /home/claude/port-libs/.upstream-cache -maxdepth 4 \( -name 'pandoc.cabal' -o -name 'pandoc-lua-engine.cabal' -o -name 'pandoc-server.cabal' -o -name 'pandoc-cli.cabal' -o -name 'cabal.project' -o -name 'cabal.project.freeze' -o -name 'test-pandoc.hs' -o -name 'test-pandoc-lua-engine.hs' \) -print | sort
passed with no output
```

Delta:

- `+0` focused PHP PASS cases.
- `+0` mapped upstream cases.
- `phpPass` remains unchanged.

Example smoke: not run, because this is an upstream-runner dependency audit-only
slice and no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The current blocker is the
absence of a hydrated pinned Pandoc checkout containing the required Cabal
project/package files and Haskell Tasty entrypoints.

Before claiming upstream runner dependency closure, hydrate or verify the pinned
checkout and rerun the lane-local static audit against real `cabal.project`,
`cabal.project.freeze`, `pandoc.cabal`, `pandoc-cli`,
`pandoc-lua-engine`, `pandoc-server`, runner entry files, benchmark entry
files, and required source/golden artifacts. Only after that audit is clean
should a separate explicitly authorized slice record the descriptor-only
non-mutating Cabal plan.

## Non-Overlap

This does not repeat accepted implementation slices for ZIP/OPC, DOCX, ODT,
EPUB, archive compression, math, citations, BibTeX, XML/HTML DOM, charset,
table geometry, PDF handoff, or legacy DOC/CFB behavior.

It also does not add another Cabal parser/audit fixture. The owned output is
only the current-base local cache/tool gate confirming that the previously
implemented audit cannot safely advance to a Cabal dry-run plan in this
worktree.

## Follow-Up

Hydrate the pinned Pandoc upstream source at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, including all Cabal package files,
`cabal.project`, and `cabal.project.freeze`. Then rerun the focused static audit
before considering any descriptor-only Cabal dry-run command in a separate
authorized slice.

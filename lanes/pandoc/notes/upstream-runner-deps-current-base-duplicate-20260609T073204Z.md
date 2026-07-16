# Upstream Runner Dependency Audit - Current Base Duplicate Gate

Micro-slice: `pandoc-upstream-runner-deps-current-base-duplicate-20260609T073204Z`
Accepted base: `df259aa2eedc94083122c4983a2ea922c64e663c`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

Current local cache discovery still found no Pandoc Cabal package or project
files under `/home/claude/port-libs/.upstream-cache`:

```text
find /home/claude/port-libs/.upstream-cache -maxdepth 5 \( -name 'pandoc.cabal' -o -name 'pandoc-lua-engine.cabal' -o -name 'pandoc-server.cabal' -o -name 'pandoc-cli.cabal' -o -name 'cabal.project' -o -name 'cabal.project.freeze' -o -name 'test-pandoc.hs' -o -name 'test-pandoc-lua-engine.hs' \) -print | sort
passed with no output
```

Tool probes:

```text
ghc --numeric-version
9.10.3

cabal --numeric-version
3.12.1.0

stack --numeric-version
passed with no output
```

No Pandoc, Cabal solver/build/test command, Haskell test binary, benchmark
executable, Stack command, Word, LibreOffice, zip/unzip, external template
engine, external converter, TeX/PDF engine, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Behavior

No new PHP implementation was added because this micro-slice is explicitly an
upstream-runner dependency audit and no safe local Cabal plan step is available
without a hydrated pinned Pandoc checkout.

The lane-local lightweight cache gate on this current base reports:

```text
status=blocked_missing_upstream_source
hydrated=no
completeRoot=none
missing=cabal.project,pandoc.cabal,pandoc-lua-engine/pandoc-lua-engine.cabal,pandoc-server/pandoc-server.cabal,pandoc-cli/pandoc-cli.cabal,test/test-pandoc.hs,pandoc-lua-engine/test/test-pandoc-lua-engine.hs
missingTools=
stablePlan=
summary=Pandoc upstream runner dependency closure is blocked because no single local checkout contains the required Cabal files and Tasty test entrypoints.
```

This keeps the dependency gate classified as missing pinned source truth, not a
missing native PHP support-library component.

## Evidence

Focused upstream-runner dependency family:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2710 assertions, 0 failures
```

Delta:

- `+0` focused PHP PASS cases.
- `+0` focused assertions.
- `+0` mapped upstream cases.
- `phpPass` remains `2497`.

Example smoke: not run, because this is an upstream-runner dependency
audit-only slice and no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The current blocker is the
absence of a hydrated pinned Pandoc checkout containing real non-empty Cabal
project/package files, Haskell Tasty entrypoints, benchmark entrypoint, source
modules, and golden/data artifacts.

Before claiming upstream runner dependency closure, hydrate or verify the
pinned checkout and rerun the lane-local static audit against real
`cabal.project`, `cabal.project.freeze`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `pandoc-server/pandoc-server.cabal`,
`pandoc-cli/pandoc-cli.cabal`, runner entry files, benchmark entry files, and
required source/golden artifacts. Only after that audit is clean should a
separate explicitly authorized slice record a descriptor-only non-mutating
Cabal plan.

## Non-Overlap

This does not repeat accepted support-library slices for ZIP/OPC, DOCX, ODT,
EPUB, archive compression, math, citations, BibTeX, XML/HTML DOM, charset,
syntax highlighting, table geometry, PDF handoff, or legacy DOC/CFB behavior.

It also does not add another Cabal parser fixture. Recent upstream-runner audit
work already covers conditional branches, package flags, non-empty source
placeholders, freeze-file content, runner/benchmark closure, Lua/server/CLI
closure, dry-run command envelopes, and workspace descriptors. The owned output
here is only the current-base duplicate cache/tool gate confirming that the
previously implemented audit still cannot safely advance to Cabal planning in
this worktree.

## Follow-Up

Hydrate the pinned Pandoc upstream source at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, including all Cabal package files,
`cabal.project`, and `cabal.project.freeze`. Then rerun the focused static PHP
audit before considering any descriptor-only Cabal dry-run command in a separate
authorized slice.

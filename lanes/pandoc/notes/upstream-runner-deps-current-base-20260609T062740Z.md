# Upstream Runner Dependency Audit - Non-Empty Cache Gate

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T062740Z`
Accepted base: `af6312fc28e410cf89483de53de7abd19ac56d73`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane
before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

Current local cache discovery found no Pandoc Cabal package or project files
under `/home/claude/port-libs/.upstream-cache`:

```text
find /home/claude/port-libs/.upstream-cache -maxdepth 4 \( -name 'pandoc.cabal' -o -name 'pandoc-lua-engine.cabal' -o -name 'pandoc-server.cabal' -o -name 'pandoc-cli.cabal' -o -name 'cabal.project' -o -name 'cabal.project.freeze' -o -name 'test-pandoc.hs' -o -name 'test-pandoc-lua-engine.hs' \) -print | sort
passed with no output
```

This slice did not execute Pandoc, Cabal solver/build/test commands, Haskell
test binaries, benchmark executables, Stack, Word, LibreOffice, zip/unzip,
external template engines, external converters, TeX/PDF engines, browser
renderers, online services, live provider tests, or live-service provider
tests.

## Behavior

`PandocUpstreamRunnerDependencyAudit` now treats required upstream source files
as present only when they are regular non-empty files. The same non-empty rule
is used for stable plan evidence, so an empty `cabal.project.freeze` is not
counted as a pinned plan file.

This keeps the lightweight current-base cache gate from advancing to
`ready_for_dependency_plan` on placeholder files such as a zero-byte
`pandoc.cabal`. If the required source files are non-empty but
`cabal.project.freeze` is empty, the audit can still identify the hydrated
source root while reporting `cabal.project.freeze` as an unpinned-plan risk in
the activation gate.

## Evidence

Red-first focused regression run before the implementation change:

```text
php tools/run-tests.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL blocks empty source placeholders and ignores empty stable plan files in lightweight cache gate (lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php)
Expected: 'blocked_missing_upstream_source'
Actual: 'ready_for_dependency_plan'
1 test files, 38 assertions, 1 failures
```

Focused direct run after the implementation change:

```text
php tools/run-tests.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 47 assertions, 0 failures
```

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2665 assertions, 0 failures
```

Delta:

- `+1` focused PHP PASS case.
- `+10` focused assertions in `PandocUpstreamRunnerDependencyAuditTest.php`.
- `phpPass` updated from `2445` to `2446`.
- Mapped upstream inventory unchanged.

Example smoke: not run, because this is an upstream-runner dependency audit and
no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing
lightweight upstream-runner dependency audit and tightens its cache-root source
evidence rule.

The current blocker remains the absence of a hydrated pinned Pandoc checkout
containing real non-empty Cabal project/package files and Haskell Tasty
entrypoints. Before claiming upstream runner dependency closure, hydrate or
verify the pinned checkout and rerun the lane-local static audit against real
`cabal.project`, `cabal.project.freeze`, package manifests, runner entry files,
benchmark entry files, and required source/golden artifacts. Only after that
audit is clean should a separate explicitly authorized slice record a
descriptor-only non-mutating Cabal plan.

## Non-Overlap

This does not repeat accepted support-library slices for ZIP/OPC, DOCX, ODT,
EPUB, archive compression, math, citations, BibTeX, XML/HTML DOM, charset,
syntax highlighting, table geometry, PDF handoff, or legacy DOC/CFB behavior.

It also does not repeat the strict Cabal package parser/source-semantics audit.
The owned output is the lightweight current-base lane/cache gate that prevents
placeholder source files from becoming runner dependency-plan evidence.

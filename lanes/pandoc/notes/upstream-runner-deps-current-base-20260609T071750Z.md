# Upstream Runner Dependency Audit - Freeze Content Risk

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T071750Z`
Accepted base: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

The local upstream cache root exists, but no hydrated
`/home/claude/port-libs/.upstream-cache/pandoc` checkout was available in this
worker. This slice therefore stays inside the native PHP static audit fixture.
It did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, benchmark executables, Stack, Word, LibreOffice, zip/unzip, external
template engines, external converters, TeX/PDF engines, browser renderers,
online services, live provider tests, or live-service provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now validates non-empty `cabal.project.freeze`
content before treating it as stable plan evidence. A freeze file still records
SHA-256 and byte-count provenance when present, but if it has no Cabal
`constraints:` stanza with pinned package constraints, the audit records:

- `planStabilityClosure.invalidFiles["cabal.project.freeze"] = "missing pinned constraints"`
- `planStabilityClosure.unpinnedPlanRisk = true`

This remains non-executing and non-mutating. It does not run Cabal and does not
claim solver output. It only prevents placeholder freeze files from being
described as stable dependency evidence before a reviewed non-mutating plan.

## Evidence

Accepted baseline focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2593 assertions, 0 failures
```

Red-first focused run after adding the regression test and before the audit
implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records invalid cabal project freeze contents as unpinned plan risk before solver planning
1 test files, 2598 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2603 assertions, 0 failures
```

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2710 assertions, 0 failures
```

JSON checks:

```text
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK" . PHP_EOL; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK
```

Delta:

- `+1` focused PHP PASS case.
- `+10` focused assertions in `UpstreamRunnerDependencyAuditTest.php`.
- `+1` mapped upstream-runner dependency audit case.
- `phpPass` updated from `2482` to `2483`.
- Mapped upstream inventory updated from `2861` to `2862`.

Example smoke: not run, because this is an upstream-runner dependency
audit-only slice and no WordPress-visible conversion path was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal fixtures, and the focused PHP
TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal dependency plan before any Haskell executable
build, runner, or benchmark execution is considered in a separate explicitly
authorized slice.

## Non-Overlap

This does not repeat accepted support-library slices for ZIP/OPC, DOCX, ODT,
EPUB, archive compression, math, citations, BibTeX, XML/HTML DOM, charset,
syntax highlighting, table geometry, PDF handoff, or legacy DOC/CFB behavior.

It also does not repeat prior upstream-runner dependency checks for required
files, package identity, setup hooks, package flags, package files,
project pins/packages/constraints/conditionals, runner and benchmark
dependencies/options/modules/artifacts/source semantics, Lua-engine library
closure, server library closure, `pandoc-cli` executable closure, dry-run
command envelopes, workspace descriptors, or freeze-file existence/provenance.
The owned behavior is only content validation for non-empty
`cabal.project.freeze` stable-plan evidence.

## Follow-Up

If upstream-runner dependency work remains active, hydrate or verify the pinned
Pandoc checkout and run the static audit against real `cabal.project`,
`cabal.project.freeze`, package manifests, runner entry files, benchmark entry
files, and required source/golden artifacts before any authorized non-mutating
Cabal plan.

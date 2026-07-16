# Upstream Runner Deps Current Base 20260609T083503Z

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T083503Z`
Accepted base: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Scope

This slice extends the static upstream runner dependency audit. It does not run Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, external converters, online services, live providers, or live-service provider tests.

## Behavior Added

- `UpstreamRunnerDependencyAudit::auditCheckout()` now exposes `cabalPlanDescriptorClosure`.
- The closure validates the descriptor-only Cabal dry-run commands for `runner-test-dependencies` and `benchmark-dependencies`.
- The activation gate now blocks if command descriptors or workspace descriptors drift away from:
  - required `v2-build`, `--offline`, `--dry-run`, and `--only-dependencies` arguments;
  - matching command `buildDirectory`, `--builddir=...`, and workspace build-directory paths;
  - repo-local `.port-libs/pandoc-runner/**` paths;
  - no default `dist-newstyle` paths;
  - no absolute, HOME-scoped, or parent-traversal paths;
  - descriptor-only environment handling with no live process environment output.

## Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 2603 assertions, 0 failures
```

Focused verification after implementation:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php

php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php

php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 2622 assertions, 0 failures
```

Delta: `+1` focused PHP PASS case, `+19` focused assertions, `+1` mapped upstream-runner dependency descriptor closure case.

## Dependency Closure

No new support component is needed. This reuses the existing static `UpstreamRunnerDependencyAudit` support class and adds native PHP descriptor validation only.

The local `.upstream-cache` did not include a Pandoc Cabal checkout in this isolated worktree, so no safe local upstream solver/build step was available or attempted. A future runner slice can hydrate or point at an approved local Pandoc checkout and use this descriptor closure as the activation gate before any reviewed dry-run dependency plan.

## Non-Overlap

This does not overlap the accepted YAML metadata merge-provenance slice, ODF subtotal/dropdown slices, DOCX/ODF/EPUB package readers, or conversion support-library work. It is limited to the upstream-runner dependency audit descriptor closure.

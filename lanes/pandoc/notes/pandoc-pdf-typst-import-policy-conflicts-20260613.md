# PDF/Typst Import Policy Conflict Summaries

Date: 2026-06-13
Issue: plib-bddfl

## Slice

`PdfEngineHandoff` fake runs now cross-check source-visible Typst
`#import`/`#include` path policy rows with `typstPackageDependencyPolicy`
sidecar package dependencies. In dynamic import contexts, the package policy
adds deterministic metadata-only summaries for literal package imports mixed
with unsupported dynamic expressions, duplicate unsupported expressions, and
source-vs-sidecar package input disagreements.

The sidecar dependency behavior remains unchanged for ordinary package-only
dependency policies. The new summaries are reviewer metadata; they do not
execute Pandoc, Typst, TeX/PDF engines, browsers, Node tooling, online
services, live providers, or external validators.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2256 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 75728 assertions, 0 failures

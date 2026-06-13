# PDF/Typst Import Path Policy Provenance

Date: 2026-06-13
Issue: plib-mne87
Base: `025c076f77`

## Slice

`PdfEngineHandoff` fake runs now inspect source-visible Typst `#import` and
`#include` directives without executing Typst, on top of the package
unsupported-reason policy slice and JSON/native raw HTML alias slice. The import
path policy preserves literal string arguments as metadata-only literal path
rows and reports variable, concatenated, and duplicate dynamic expressions as
metadata-only unsupported path policy rows.

The scanner ignores directives inside Typst comments and string literals. It
also preserves the existing sidecar `typstPackageDependencyPolicy` behavior, so
dependency depfiles still drive package namespace/package/version provenance.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2252 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 75556 assertions, 0 failures`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
Node tooling, external validator, online service, live provider test, or
live-service provider test was invoked.

## Counters

- `phpPass`: `3353 -> 3354`
- `phpFail`: `0`
- `mapped`: `3313 -> 3314`
- `mappedTypstImportPathPolicyCases`: `1`
- `typstImportPathPolicyAssertions`: `15`

## Remaining Gaps

PDF/Typst remains partial. Full Typst reader support and full PDF output parity
are still outside this bounded no-engine provenance slice.

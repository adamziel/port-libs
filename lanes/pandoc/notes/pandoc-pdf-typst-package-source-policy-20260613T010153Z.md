# pandoc-pdf-typst-package-source-policy-20260613T010153Z

Slice: `plib-15bdf`, PDF/Typst package source-class provenance.

Base: current main `d1cb8cab8d`, after CSV/TSV header option parity.

`PdfEngineHandoff` fake runs now classify Typst package dependency provenance by
source bucket while preserving the richer package dependency policy fields from
current main. Structured `engineTypstPackageDependencies` rows carry
`sourceClass`, and `typstPackageDependencyPolicy` serializes deterministic
`sourceClasses` plus `sourceClassCounts` for `custom-namespace`,
`preview-registry`, and `typst-registry` dependencies. Fake-run diagnostics now
include entries such as `typst-package-dependency-source:preview-registry:1`.

This is not a shippable Typst/PDF output implementation. Native PHP still does
not execute Pandoc, Typst, TeX, PDF engines, browser renderers, Node tooling,
online validators, live providers, or external validators. The supported value
is bounded no-engine provenance for package-policy review queues.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2231 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `45 test files, 75045 assertions, 0 failures`

Parity accounting: one new focused PHP PASS case after the Typst dependency
conflict-policy baseline. `phpPass` moves `3335 -> 3336`, `phpFail` remains
`0`, and `UPSTREAM_TEST_MANIFEST.json` mapped cases move `3294 -> 3295`.

Remaining gaps: native Typst reader support is still absent, and full PDF/Typst
output parity remains blocked on external engine execution. Keep future work to
bounded native provenance/reporting slices or separate reader implementation
work.

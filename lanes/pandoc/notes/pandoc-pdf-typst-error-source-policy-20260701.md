# pandoc-pdf-typst-error-source-policy-20260701

Slice: `plib-k4zho`, PDF/Typst boundary provenance.
Date: 2026-07-01 UTC.

## Behavior

`PdfEngineHandoff` now records Typst error diagnostic source provenance in the
fake PDF engine runner path. JSON and text Typst error diagnostics are bucketed
by source kind, source class, root-boundary status, package reference, and
source issue before artifact review handoff.

The existing warning source policy remains intact; the new error source policy
uses parallel `typstErrorProvenance`, `typstErrorSourcePolicy`, and
`error-provenance` boundary matrix fields.

No Pandoc, Typst, TeX/PDF engine, browser renderer, unzip/zip tool, Node tool,
office suite, or external validator is executed.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstErrorSourcePolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstErrorSourcePolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstWarningSourcePolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryMatrixSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstOpenOutputViewerPolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

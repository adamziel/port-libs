# PDF/Typst diagnostic span policy

`PdfEngineHandoff` now carries additive metadata-only source span rollups for
Typst warning and error provenance. The existing diagnostic parsers already
preserved line, column, end-line, end-column, and hint details; this slice rolls
those into reviewer-facing source policies, boundary matrix details, diagnostics,
artifact provenance review, and fake-run sequence handoff.

The packet exposes source location counts, ranged source counts, hint counts,
stable `sourceFile:line:column` keys, and ranged source file lists without
executing Typst, PDF engines, Pandoc, browser tooling, or external validators.

## Manifest

- `mappedTypstDiagnosticSpanPolicyCases`: `1`
- `typstDiagnosticSpanPolicyAssertions`: `48`
- `mapped`: `2883 -> 2884`

## Validation

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticSpanPolicyTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticSpanPolicyTest.php`

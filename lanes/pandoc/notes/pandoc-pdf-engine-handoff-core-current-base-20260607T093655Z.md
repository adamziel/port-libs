# Pandoc PDF Engine Handoff Current-Base Name Trees

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260607T093655Z`
Base: `b86d159cdf99a07a68249d9af6c697b1a15bfa78`

## Scope

This slice adds bounded native PHP inventory for produced-PDF catalog `/Names`
name trees in `PdfEngineHandoff` fake-runner summaries. It does not execute
Pandoc, TeX/PDF engines, Typst, roff, browser renderers, JavaScript, external
PDF validators, online services, live provider tests, or live-service provider
tests.

The new handoff fields are:

- `pdfNameTrees` on fake-runner results.
- `finalPdfNameTrees` on multipass sequence summaries.
- Diagnostics `pdf-byte-name-trees:<count>`,
  `pdf-byte-name-tree:<category>:<entryCount>`, and
  `pdf-byte-name-tree-kids:<kidCount>`.

Each name-tree inventory record preserves the category, source reference,
bounded names, entry count, value kind counts, value references, kid count, and
limits. This complements existing specialized PDF handoffs for named
destinations, embedded files, JavaScript active actions, catalog URI base, XMP,
PDF/A, PDF/UA, tagged structure, outlines, annotations, and page metadata
without reworking those paths.

## Evidence

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 709 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 719 assertions, 0 failures`.
- Added `1` PHP PASS case and `10` focused assertions.
- `lane-status.json` `phpPass` moves from `1484` to `1485`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `1902` to `1903`;
  `pdfEngineHandoffCoreCases` and `mappedPdfEngineHandoffCoreCases` move from
  `12` to `13`; `pdfEngineHandoffCoreAssertions` moves from `108` to `118`.

## Dependency Closure

No new support component is needed. The slice reuses native
`PdfEngineHandoff` PDF byte inspection, existing bounded PDF
object/dictionary/array parsing helpers, fake-runner file-map/result plumbing,
the focused PDF handoff test, and the WordPress PDF engine handoff example.

Full PDF name-tree conformance validation, renderer execution, JavaScript
execution, external PDF validation, and upstream Pandoc/Haskell runner parity
remain out of scope for this isolated micro-slice.

## Next

Next PDF engine work should stay non-overlapping, such as destination option
metadata, page resource inheritance gaps, or safer bounded stream metadata.

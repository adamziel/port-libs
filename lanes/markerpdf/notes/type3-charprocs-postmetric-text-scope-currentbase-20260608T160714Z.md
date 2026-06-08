# Type3 CharProcs Post-Metric Text Scope Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T160714Z`  
Base accepted HEAD: `7bb9457c376694c6a19cdc4541a59964cc2f5d73`

## Source Truth

Upstream markerPDF routes searchable-PDF text extraction through pdftext/PDFium
before OCR/model fallback. In this native no-GPU PHP lane, Type3 `/CharProcs`
remain glyph programs: a valid `d0`/`d1` width may drive WordPress paragraph
spacing, but malformed text-object scope after the metric should fail closed to
font `/Widths` rather than making a bad glyph program authoritative.

This slice covers a boundary not handled by the existing post-metric scope
coverage: unmatched `ET` and nested `BT` after a valid metric. Valid
post-metric `BT`/`ET` painting remains accepted and private.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricTextScopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed post-metric Type3 CharProc text scopes before WordPress text grouping on current base
Expected: ['GoodWide', 'Scope Gap', 'Nest Gap']
Actual: ['GoodWide', 'ScopeGap', 'NestGap']
1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::type3CharProcOpenScopesCloseAfterMetric()` now rejects:

- nested `BT` while already inside a post-metric text object;
- unmatched `ET` outside a post-metric text object.

The change is limited to the post-metric structural scanner. Existing valid
post-metric text painting and no-paint pre-metric text setup continue to pass.

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricTextScopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed post-metric Type3 CharProc text scopes before WordPress text grouping on current base
1 test files, 13 assertions, 0 failures
```

Adjacent scope/text-object run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOpenScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php
5 test files, 62 assertions, 0 failures
```

Broad Type3/extractor run:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProcs*Test.php' -o -name 'PdfFontType3CharProc*Test.php' -o -name 'PdfFontSimpleType3*Test.php' -o -name 'PdfFontCMapCidType3*Test.php' -o -name 'PdfFontCidType3*Test.php' -o -name 'PdfImageXObjectType3CharProc*Test.php' -o -name 'PdfPageResourceDuplicateType3FontCurrentBaseTest.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
74 test files, 1517 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-postmetric-text-scope-currentbase.php
```

The smoke exits `0` and emits `GoodWide`, `Scope Gap`, `Nest Gap`,
`valid_postmetric_text_width_preserved=true`,
`postmetric_unmatched_et_rejected=true`, `postmetric_nested_bt_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer,
object scanner, Type3 CharProc width parser, `/Widths` fallback path, text
advance grouping, TestRunner harness, and WordPress smoke renderer. GPU/model
execution, OCR, Surya/Texify/Torch, pypdfium/PDFium rendering,
Streamlit/FastAPI workers, live services, and external PDF tools remain
intentionally out of scope.

## Non-Overlap

This does not repeat accepted direct `d0`/`d1` metrics, duplicate metric
rejection, metrics hidden inside text objects, pre-metric text setup, unclosed
pre-metric graphics/marked-content scopes, post-metric unmatched `Q`/`EMC`/`EX`,
compatibility sections, inline-image paint rejection, path/color/text-state
setup, FontMatrix/width-vector normalization, glyph array values, resource
fallback exclusion, CMap/CIDSet Type3 spacing, Type3 image review, xref repair,
metadata, annotations, forms, image filters, OCR/model execution, or supplied
table/equation handoffs. The bounded behavior is malformed post-metric text
scope after a valid Type3 metric.

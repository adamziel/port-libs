# Type3 CharProcs post-metric scope boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T145053Z`

Base accepted HEAD: `809a2da5c6b7ac8981b6fadaa6d9b301c311a0e2`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before model handoffs. In the native no-GPU PHP scope, Type3 `/CharProcs`
remain glyph programs: `d0` and `d1` may provide glyph metrics, but malformed
glyph streams should fail closed to fallback `/Widths` before WordPress
paragraph grouping. This slice covers the structural tail after the metric:
balanced post-metric `q`/`Q` painting is allowed, but unmatched `Q`, `EMC`, or
`EX` after the metric must not make a malformed glyph width authoritative.

## Behavior

- valid post-metric `q ... Q` painting after `d0` keeps `GoodWideR` grouped
  through the Type3 CharProc width;
- unmatched post-metric `Q` rejects the Type3 CharProc width so `Rest Gap`
  uses fallback `/Widths`;
- unmatched post-metric `EMC` rejects the Type3 CharProc width so `Marked Gap`
  uses fallback `/Widths`;
- unmatched post-metric `EX` rejects the Type3 CharProc width so `Compat Gap`
  uses fallback `/Widths`;
- CharProc payload text remains excluded from visible WordPress paragraphs;
- no Python, OCR/model, GPU, raster, or external PDF tooling is invoked.

## Red-first evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects unbalanced post-metric Type3 CharProc scopes before WordPress text grouping on current base
Expected: ['GoodWideR', 'Rest Gap', 'Marked Gap', 'Compat Gap']
Actual: ['GoodWideR', 'RestGap', 'MarkedGap', 'CompatGap']
1 test files, 1 assertions, 1 failures
```

## Verification

Focused run after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects unbalanced post-metric Type3 CharProc scopes before WordPress text grouping on current base
1 test files, 16 assertions, 0 failures
```

Adjacent Type3 CharProc scope/metric run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOpenScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 123 assertions, 0 failures
```

Broader Type3/font extractor run:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*Type3*Test.php' -o -name '*CharProc*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 73 selected test files (root lock skipped)
73 test files, 1494 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-postmetric-scope-boundary-currentbase.php
```

The smoke emits `GoodWideR`, `Rest Gap`, `Marked Gap`, `Compat Gap`,
`valid_postmetric_scope_width_preserved=true`,
`postmetric_unmatched_q_rejected=true`,
`postmetric_unmatched_emc_rejected=true`,
`postmetric_unmatched_ex_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PDF content
tokenizer, Type3 CharProc width parser, `/Widths` fallback, and WordPress block
smoke paths already present under `lanes/markerpdf/**`. GPU/model execution,
OCR, pypdfium/PIL rendering, Streamlit/FastAPI workers, and external PDF tools
remain intentionally out of scope for markerPDF under the current no-GPU
directive.

## Non-overlap

This does not repeat accepted Type3 direct `d0`/`d1` width handling, duplicate
metric rejection, metrics hidden inside text objects, pre-metric unmatched
`Q`/`EMC`, unclosed scopes opened before the metric, compatibility-section
admission before metrics, text-object setup validation, inline-image paint
rejection before metrics, path/color/text-state operand validation, resource
fallback exclusion, FontMatrix/width-vector normalization, CMap/CIDSet Type3
spacing, xref/object-stream parser behavior, annotations, forms, metadata,
image filters, OCR/model execution, or supplied-boundary table/equation work.
The bounded behavior is post-metric structural scope balance before Type3
glyph metrics can override fallback `/Widths`.

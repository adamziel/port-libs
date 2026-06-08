# Type3 CharProcs Text Object Metric Boundary Current Base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T135128Z`

Base accepted HEAD: `95ed9a719a03101e72b33de7de15d86db46d9a80`

## Source Truth

Type3 CharProc `d0` and `d1` operators define glyph metrics. They are not text-object operators, so a second `d0` or `d1` hidden inside a post-metric `BT`/`ET` block is still a duplicate glyph metric and must fail closed for width extraction. For markerPDF's native searchable-PDF path, accepting that hidden metric under-advances WordPress paragraph spacing and styled span grouping. CharProc payload text remains glyph-private fallback content and must not leak into visible Gutenberg paragraphs.

## Implementation

- `PdfTextExtractor::type3CharProcHasAdditionalMetricOperator()` now detects `d0` and `d1` tokens even while scanning a post-metric text object.
- A new focused fixture proves a valid initial Type3 `d0` still supplies a wide glyph, while hidden duplicate `d0` and `d1` operators inside post-metric `BT`/`ET` text objects are rejected so `/Widths` fallback preserves `Hidden Gap` and `Metric Gap`.
- Added a WordPress smoke that emits the three expected paragraphs and review flags without Python, OCR, pypdfium/PIL, external PDF tools, or model execution.

## Red-First Evidence

Before the parser patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php`

Result: failed with actual lines `GoodWide`, `HiddenGap`, `MetricGap` instead of expected `GoodWide`, `Hidden Gap`, `Metric Gap`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php` => 2 test files, 30 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php` => 4 test files, 53 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*Test.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php` => 71 test files, 849 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-text-object-metric-currentbase.php` => exits 0 and emits `valid_charproc_width_preserved=true`, `hidden_d0_text_object_metric_rejected=true`, `hidden_d1_text_object_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`, `php -l lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-text-object-metric-currentbase.php` report no syntax errors.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF content tokenizer, Type3 CharProc width parser, simple-font `/Widths` fallback, and WordPress smoke pattern. GPU/model execution, OCR, pypdfium/PIL rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted direct duplicate `d0`/`d1` metrics, Type3 FontMatrix/vector width handling, pre-metric text painting rejection, text-object setup acceptance, inline-image paint rejection, path setup, graphics/marked-content scope balancing, private resource fallback exclusion, CMap/source-width behavior, xref repair, stream filters, annotations, forms, metadata, images, OCR/model execution, or supplied-boundary table/equation work. The new boundary is specifically duplicate Type3 metrics hidden inside a post-metric `BT`/`ET` text object.

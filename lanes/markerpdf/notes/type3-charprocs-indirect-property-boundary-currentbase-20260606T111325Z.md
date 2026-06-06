# Type3 CharProcs Indirect Property Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T111325Z`

Base accepted HEAD: `f39ce62b4ff14c620afc3249e4a17d9406530380`

## Source Truth

The markerPDF no-GPU lane routes searchable PDF text through the native PHP PDF text extractor before WordPress block conversion. Type3 CharProc glyph programs can contain marked-content setup before their required `d0`/`d1` metrics. The existing parser already accepted direct dictionary and named marked-content properties before Type3 metrics and already resolves indirect property references in the broader marked-content/optional-content boundary. This slice makes the Type3 metric parser handle the same syntactic boundary for `/Tag n 0 R BDC` and `/Tag n 0 R DP`.

## Behavior

Before this patch, the generic content tokenizer treated `R` as an operator inside a CharProc, so valid indirect property operands caused the Type3 width parser to fail closed and fall back to stale `/Widths` values. That introduced false word gaps such as `Indirect Ok` and `Point Ok`.

`PdfTextExtractor` now queues `R` as an operand only after a safe `/tag object generation` sequence and accepts that four-token property reference for Type3 `BDC`/`DP` setup before `d0`/`d1`. Numeric and malformed property operands still fail closed, CharProc payload text remains excluded, and indirect property decoys are not promoted to visible WordPress paragraphs.

## Evidence

Red-first focused run after adding the test before source changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectPropertyBoundaryCurrentBaseTest.php`

Result: `FAIL accepts indirect Type3 CharProc BDC DP property references before metrics on current base`; expected `["IndirectOk","PointOk","Bad Gap"]`, actual `["Indirect Ok","Point Ok","Bad Gap"]`; `1 test files, 1 assertions, 1 failures`.

Focused passing run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectPropertyBoundaryCurrentBaseTest.php`

Result: `PASS accepts indirect Type3 CharProc BDC DP property references before metrics on current base`; `1 test files, 13 assertions, 0 failures`.

Adjacent Type3 marked-content run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php`

Result: `5 test files, 53 assertions, 0 failures`.

Broader Type3 font gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php`

Result: `47 test files, 433 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-indirect-property-currentbase.php`

Result: emitted `indirect_bdc_property_width_preserved=true`, `indirect_dp_property_width_preserved=true`, `numeric_bdc_property_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `marked_content_property_decoy_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct BDC dictionary/name properties, malformed numeric BDC rejection, marked-content balance checks, MP/DP direct dictionary handling, Type3 comment/reference parsing, CharProc generation selection, Type3 FontMatrix vector advance, private resource fallback exclusion, image/filter review, CMap/font width work, xref repair, OCR/model execution, or runtime converter boundaries. The slice is limited to indirect marked-content property references before Type3 CharProc metrics.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, content tokenizer, Type3 CharProc width parser, text advance grouping path, and WordPress smoke harness. GPU/OCR/model execution and external PDF tools remain intentionally out of scope.

# markerPDF Type3 CharProcs sibling generation boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T222401Z`

Accepted base: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Source truth

Upstream markerPDF gets searchable PDF text through PDF parser/text layers before Markdown and WordPress-visible paragraphs are assembled. Type3 `/CharProcs` streams are font glyph programs, not page content. The native no-GPU fallback stream scanner must therefore keep glyph-program streams private even when a malformed PDF leaves a stale same-object-number `/CharProcs` dictionary generation in the file.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSiblingGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps stale same-object Type3 CharProcs dictionary generations private during fallback extraction on current base
Values are not identical
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'STALE SIBLING GLYPH LEAK',
  1 => 'Visible fallback content',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::charProcObjectReferencesFromCharProcsValueForFallbackExclusion()` now keeps metric extraction strict but broadens only the stream-only fallback privacy inventory. When a Type3 font references an indirect `/CharProcs` dictionary, fallback exclusion now also scans direct sibling generations of the same object number for glyph stream references. That prevents stale sibling CharProcs dictionaries from promoting glyph payload text into WordPress paragraphs while normal page `/Contents` extraction and exact-generation width parsing remain unchanged.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSiblingGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps stale same-object Type3 CharProcs dictionary generations private during fallback extraction on current base

1 test files, 7 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsArrayWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDirectDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceValueWrapperBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 72 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php
Focused test run: 80 selected test files (root lock skipped)
80 test files, 1040 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-sibling-generation-currentbase.php
```

The smoke exits 0 and emits `visible_fallback_content_preserved=true`, `current_generation_glyph_payload_excluded=true`, `stale_sibling_generation_glyph_payload_excluded=true`, `font_program_name_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, rendering only `Visible fallback content`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, direct-generation object inventory, exact object lookup, Type3 font detection, CharProcs dictionary walker, stream decoder, focused PHP runner, and WordPress smoke harness. Python, pdftext, pypdfium/PDFium, OCR/model workers, GPU/model execution, and external PDF tools remain intentionally out of scope.

## Non-overlap

This does not repeat accepted direct CharProc stream fallback exclusion, exact selected CharProcs dictionary generation width parsing, array-wrapped `/CharProcs` handling, direct/indirect dictionary tail rejection, glyph-entry tail rejection, duplicate glyph-tail replacement, resource value wrapper fallback, Type3 width-vector parsing, FontMatrix scaling, CMap/CIDSet grouping, image XObject review, xref repair, annotations, forms, metadata, security preflight, table/equation handoffs, or OCR/model execution. The bounded behavior is only stream-only fallback exclusion for glyph streams reachable from stale direct sibling generations of a selected Type3 `/CharProcs` dictionary object.

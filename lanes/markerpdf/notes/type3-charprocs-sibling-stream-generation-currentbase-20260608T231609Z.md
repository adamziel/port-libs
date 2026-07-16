# Type3 CharProcs Sibling Stream Generation Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T231609Z`

Accepted base: `2e9d106a5085fd98176497cfade7ca0a16be2709`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text layers before OCR/model fallback. Type3 `/CharProcs` streams are font glyph programs, not document paragraphs. In the native no-GPU PHP lane, stream-only fallback extraction must treat every direct generation of the referenced `/CharProcs` object as Type3-private when a sibling generation is a malformed stream object.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSiblingStreamGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps stale same-object Type3 CharProcs stream generations private during fallback extraction on current base
Values are not identical
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'Visible fallback content',
  1 => 'STALE CHARPROCS STREAM RESOURCE LEAK',
  2 => 'STALE CHARPROCS STREAM GENERATION PAYLOAD LEAK',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::type3CharProcsDictionaryReferencesFromValueForFallbackExclusion()` now adds direct sibling generations of a referenced Type3 `/CharProcs` object to the fallback-private dictionary generation set. This keeps malformed stale sibling `/CharProcs` stream payloads and their private resources out of fallback WordPress text while preserving exact-generation Type3 metric parsing.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSiblingStreamGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps stale same-object Type3 CharProcs stream generations private during fallback extraction on current base

1 test files, 10 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php
Focused test run: 81 selected test files (root lock skipped)
81 test files, 1050 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-sibling-stream-generation-currentbase.php
```

The smoke exits 0 and emits `visible_fallback_content_preserved=true`, `current_generation_glyph_payload_excluded=true`, `stale_sibling_generation_glyph_payload_excluded=true`, `stale_sibling_charprocs_stream_payload_excluded=true`, `stale_sibling_resource_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, rendering only `Visible fallback content`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, direct-generation object inventory, exact object lookup, Type3 font detection, CharProcs dictionary fallback-exclusion walker, resource stream exclusion walker, stream decoder, focused PHP runner, and WordPress smoke harness. Python, pdftext, pypdfium/PDFium, OCR/model workers, GPU/model execution, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted direct CharProc stream fallback exclusion, exact selected CharProcs dictionary generation width parsing, stale sibling CharProcs dictionary glyph-reference exclusion, array-wrapped `/CharProcs` handling, direct/indirect dictionary tail rejection, glyph-entry tail rejection, duplicate glyph-tail replacement, resource value wrapper fallback, Type3 width-vector parsing, FontMatrix scaling, CMap/CIDSet grouping, image XObject review, xref repair, annotations, forms, metadata, security preflight, table/equation handoffs, or OCR/model execution. The bounded behavior is only stream-only fallback exclusion for stale same-object Type3 `/CharProcs` stream generations and their private resources.

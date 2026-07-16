# Type3 CharProcs Array Wrapper Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T112538Z`

Base accepted HEAD: `7e21de178185310eed544e75b51e958afd2998f5`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text layers before OCR or model fallback. In this no-GPU PHP lane, Type3 `/CharProcs` are font glyph programs: valid `d0`/`d1` metrics may drive text advance grouping, but glyph program streams are private and must not be promoted to visible WordPress paragraphs.

The PDF Type3 `/CharProcs` value is a dictionary. An array-wrapped value such as `/CharProcs [21 0 R]` is malformed for metric extraction and should fail closed to ordinary `/Widths`/`MissingWidth` behavior. The privacy boundary is stricter: even malformed wrappers can name CharProc dictionaries or stream dictionaries, and those referenced glyph streams remain font-private during stream-only fallback extraction.

## Change

- `PdfTextExtractor::charProcObjectReferencesFromCharProcsValueForFallbackExclusion()` now walks direct and indirect array-wrapped `/CharProcs` values while keeping the accepted metric parser strict.
- `PdfTextExtractor::type3CharProcsDictionaryReferencesForFallbackExclusion()` now records immediate references reachable through malformed array wrappers so invalid CharProcs stream-object payloads are also excluded from fallback extraction.
- Both array walkers carry reference-cycle guards and reuse the existing unique PDF reference inventory.

## Evidence

Red-first focused test before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsArrayWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects array-wrapped Type3 CharProcs dictionaries before WordPress text grouping on current base
FAIL keeps array-wrapped Type3 CharProcs glyph streams private during fallback extraction on current base
Values are not identical
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'ARRAY WRAPPED CHARPROCS GLYPH LEAK',
  1 => 'Visible fallback content',
)

1 test files, 7 assertions, 1 failures
```

Passing focused test after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsArrayWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects array-wrapped Type3 CharProcs dictionaries before WordPress text grouping on current base
PASS keeps array-wrapped Type3 CharProcs glyph streams private during fallback extraction on current base
PASS keeps array-wrapped Type3 CharProcs stream dictionaries private during fallback extraction on current base

1 test files, 19 assertions, 0 failures
```

Focused Type3 CharProc family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php
Focused test run: 66 selected test files (root lock skipped)
66 test files, 803 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-array-wrapper-currentbase.php
```

Result: exits 0 and emits `array_wrapped_charprocs_rejected=true`, `fallback_widths_preserve_word_gap=true`, `fallback_content_preserved=true`, `fallback_glyph_payload_excluded=true`, `fallback_stream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, rendering only `Bad Path`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, PDF array parser, exact object lookup, Type3 CharProcs dictionary parser, fallback stream exclusion inventory, text advance grouping path, focused PHP runner, and WordPress smoke harness. GPU/OCR/model execution, Python/PDFium runtime, raster rendering, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted Type3 CharProc fallback-payload exclusion, direct or indirect `/CharProcs` dictionary tail rejection, glyph-entry tail rejection, duplicate glyph-tail replacement, duplicate top-level `/CharProcs` key precedence, indirect dictionary generation selection, stream-object dictionary fallback exclusion, dictionary comments, top-level/nested dictionary parsing, comment-split references, encoding generation/comment parsing, duplicate Type3 font subtype selection, FontMatrix/width precedence, D1 bbox operands, marked-content/graphics-state/path/text-object setup, inline-image paint rejection, image XObject review, resource fallback exclusion, CMap/CIDSet width behavior, xref repair, metadata, annotations, forms, OCR/model execution, or supplied table/equation handoffs. The bounded behavior is only malformed array-wrapped Type3 `/CharProcs` values before fallback stream extraction.

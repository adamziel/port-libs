# markerpdf malformed CMap unclosed-array filter boundary current-base 2026-06-08T181602Z

## Scope

- Lane: `markerpdf`
- Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T181602Z`
- Base accepted HEAD: `534fb2b945ebdc32c342cdd704645a51abaea864`
- Native no-GPU scope only: searchable-PDF text extraction, filtered ToUnicode CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, raster rendering, or external PDF tools.

## Behavior

PDF CMap arrays must remain non-operator containers. The parser already skipped well-formed CMap arrays while scanning for `beginbfchar`, `beginbfrange`, and `end*` operators, but an unterminated array fell back to byte-by-byte scanning. That allowed fake mapping blocks inside the malformed array payload to be treated as top-level ToUnicode mappings after the stream successfully decoded.

This slice makes the CMap operator scanner fail closed once a `[` array cannot be closed. Later tokens in that malformed container are no longer treated as top-level CMap operators, so fallback searchable text is preserved and decoded CMap program bytes remain review-only.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnclosedArrayFilterBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores filtered ToUnicode bfchar blocks inside unclosed CMap arrays before current-base text extraction
Expected: array (0 => 'Unclosed Array Char Safe Import')
Actual: array (0 => 'Unclosed Array Char CMap Leaknclosed Array Char Safe Import')
FAIL ignores filtered ToUnicode bfrange blocks inside unclosed CMap arrays before current-base text extraction
Expected: array (0 => 'Unclosed Array Range Safe Import')
Actual: array (0 => 'Unclosed Array Range CMap Leaknclosed Array Range Safe Import')

1 test files, 2 assertions, 2 failures
```

## Implementation

- `PdfTextExtractor::nextCMapOperatorOffset()` now stops scanning when it encounters an unterminated CMap array instead of allowing operators inside the malformed array body to be discovered as top-level operators.
- Added `PdfParserMalformedCMapUnclosedArrayFilterBoundaryCurrentBaseTest.php` with Flate-decoded ToUnicode `bfchar` and `bfrange` fixtures inside an unclosed array.
- Added `wordpress-pdf-malformed-cmap-unclosed-array-filter-currentbase.php` to prove WordPress paragraph import preserves safe text and does not execute Python/models or external PDF tools.

## Verification

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnclosedArrayFilterBoundaryCurrentBaseTest.php
```

```text
1 test files, 88 assertions, 0 failures
```

Adjacent CMap operator/filter family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnclosedArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php
```

```text
6 test files, 377 assertions, 0 failures
```

Broader malformed CMap/filter sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMap*Filter*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilter*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php
```

```text
51 test files, 5222 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-unclosed-array-filter-currentbase.php
```

The smoke exits 0, emits two WordPress paragraphs, and reports `safe_text_preserved=true`, `unclosed_array_bfchar_rejected=true`, `unclosed_array_bfrange_rejected=true`, `payload_excluded=true`, `decoded_cmap_counts=[1,1]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted scalar/null/array/indirect `/Filter` operand failures, duplicate Filter/DecodeParms declarations, escaped filter keys, malformed DecodeParms, explicit EOD filter boundaries, post-Length operands, malformed declared counts, underdeclared rows, codespace filtering, `endcmap` inside well-formed arrays or procedures, literal-string operator decoys, `usecmap`/`WMode` procedure decoys, Type0 Encoding CID row-slot handling, xref repair, image/filter review, forms, annotations, metadata, OCR, or model work. The bounded behavior is specifically successfully decoded filtered ToUnicode CMap operators hidden inside an unterminated array container.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap operator scanner, ToUnicode fallback path, review metadata extractor, focused test harness, and WordPress smoke renderer. GPU/model OCR, PDFium/pypdfium/PIL rendering, Surya, Texify, Torch, live-service workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.

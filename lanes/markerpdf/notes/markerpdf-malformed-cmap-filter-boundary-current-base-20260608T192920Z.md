# markerpdf malformed CMap filter boundary current-base 2026-06-08T192920Z

## Scope

- Lane: `markerpdf`
- Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T192920Z`
- Base accepted HEAD: `520f0ce7b08b30848beed1a62b07a69292c33e03`
- Native no-GPU scope only: searchable-PDF text extraction, filtered ToUnicode CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, or external PDF tools.

## Behavior

PDF CMap `begincodespacerange` blocks require a preceding integer row count. The native CID CMap parser already failed closed when that count was missing or malformed, but the ToUnicode parser still accepted the local codespace rows and then applied `beginbfchar` mappings from the same malformed filtered CMap stream.

This slice makes local ToUnicode codespace handling match the CID path without regressing valid recovery: malformed local codespace blocks do not seed code-space ranges, so local ToUnicode mappings after a CMap with no valid local codespace block are skipped. Later well-formed local codespace blocks and existing base mappings from valid `usecmap` sources remain available.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingCodespaceCountFilterBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects filtered ToUnicode mappings after missing codespace declared counts before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapMissingCodespaceCountFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Missing Codespace Count Safe Import',
)
Actual: array (
  0 => 'Missing Codespace Count CMap Leakissing Codespace Count Safe Import',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::parseCMapCodeSpaceRanges()` now skips codespace blocks whose declared count is missing, non-integer, negative, or otherwise malformed instead of letting those malformed rows seed ToUnicode source matching.
- Added `PdfParserMalformedCMapMissingCodespaceCountFilterBoundaryCurrentBaseTest.php` with missing, name, array, and boolean codespace-count operands on Flate-decoded ToUnicode streams.
- Added `wordpress-pdf-malformed-cmap-missing-codespace-count-currentbase.php` to prove WordPress paragraph import preserves safe text and keeps decoded CMap program text review-only.

## Verification

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingCodespaceCountFilterBoundaryCurrentBaseTest.php
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects filtered ToUnicode mappings after missing codespace declared counts before current-base text extraction

1 test files, 180 assertions, 0 failures
```

Adjacent malformed CMap/filter family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingCodespaceCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayTargetOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
```

```text
10 test files, 2441 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-missing-codespace-count-currentbase.php
```

Result: exits 0 and emits `safe_text_preserved=true`, `missing_codespace_count_rejected=true`, `non_integer_codespace_count_rejected=true`, `filters=["FlateDecode"]`, `decoded_with_current_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat earlier malformed CMap filter-boundary slices for scalar or array `/Filter` operands, post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, UseCMap inheritance, WMode, malformed `bfchar` same-width codespace source keys, underdeclared codespace row counts, missing `bfchar`/`bfrange` declared counts, short or overlong `bfrange` target arrays, nested array row tails, Encoding CMap missing codespace-count source-width fallback, overlong source-width fallback, or Type0 Encoding CID CMap declared-count row slots. The new boundary is specifically a successfully decoded filtered ToUnicode CMap whose local `begincodespacerange` block has no valid integer declared count.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object scanner, stream decoder, CMap parser, source-width fallback path, review metadata extractor, focused test harness, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, pypdfium/PIL, Surya, Texify, Torch, live-service workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.

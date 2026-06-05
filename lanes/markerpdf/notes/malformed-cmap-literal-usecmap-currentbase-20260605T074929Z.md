# Malformed CMap Literal UseCMap Boundary - 2026-06-05

## Slice

- Lane: markerpdf
- Micro-slice: markerpdf-malformed-cmap-filter-boundary-current-base-20260605T074929Z
- Accepted base: a5beba028ee67f9e2b345ca98850eb5e99992bd4

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to the native PDF text stack behind `marker/pdf/extract_text.py` and related pdftext/PDFium behavior. Under the current no-GPU markerPDF scope, this PHP lane owns the native parser boundary for searchable PDFs, including stream filters and CMap inheritance, without launching OCR/model workers.

## Behavior

Decoded CMap streams can legally contain literal strings, hex strings, arrays, dictionaries, and comments before or around top-level CMap operators. The previous `usecmap` scan used a regex over the decoded CMap bytes, so a literal string such as `(/LiteralUseCMapDecoyBase-H usecmap)` could be mistaken for an inherited base CMap after the real top-level `/LiteralUseCMapRealBase-H usecmap` operator.

`PdfTextExtractor` now scans CMap tokens before UseCMap inheritance and skips comments, literal strings, hex strings, arrays, and dictionaries. Only a real top-level PDF name token followed by the `usecmap` keyword is accepted for both ToUnicode CMaps and CID CMaps.

## Evidence

Red-first probe before the source change:

- In-memory fixture with a filtered derived ToUnicode CMap, real top-level `/LiteralUseCMapRealBase-H usecmap`, and literal-string decoy `(/LiteralUseCMapDecoyBase-H usecmap)` extracted `Literal UseCMap Leak` instead of `Literal UseCMap Safe Import`.

Focused verification after the source change:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-usecmap-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` => 1 test files / 826 assertions / 0 failures
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` => 4 test files / 1645 assertions / 0 failures
- `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-usecmap-currentbase.php` => `decoded_cmap_count=3`, `literal_usecmap_decoy_excluded=true`, `real_base_mapping_preserved=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat the prior malformed CMap filter operands, malformed DecodeParms, null filter alignment, Crypt filter, unsupported CMap filters, post-`endcmap` operators, CMapName literal boundary, CMap comments, source-width fallback, CIDFont width CMap resources, xref repair, metadata, image filters, annotations, forms, or supplied table/OCR/equation handoff slices. It is limited to token-aware `usecmap` discovery after CMap stream decoding and before inherited CMap parsing.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object scanner, stream decoder, CMap parser, and WordPress smoke path. Python, OCR, Surya/Texify/Torch/model workers, pypdfium, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next Task

Continue non-overlapping native searchable-PDF parser work around CMap/font edge cases, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter review metadata, and supplied-boundary table/equation handoffs.

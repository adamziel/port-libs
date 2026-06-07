# markerPDF CMap negative declared-count source-width fallback

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T070429Z`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text engines before model fallback. In this native no-GPU lane, Type0 `/Encoding` CMap rows are the source-code-to-CID boundary used before descendant CIDFont `/W` and `/DW` metrics drive WordPress text grouping. CMap operator block row counts must be honored before stale or malformed rows alter source-width geometry.

## Behavior

`PdfTextExtractor::cMapDeclaredOperatorCountBefore()` now treats delimiter-separated negative CMap declared counts as explicit malformed counts. Existing block parsers already slice declared counts through `max(0, $declaredCount)`, so negative counts now fail closed to zero rows instead of being treated as missing counts.

The focused fixtures put valid CID mappings first, then append `-4 begincidchar` or `-1 begincidrange` blocks whose rows point the same source bytes to wide decoy CIDs. Before the patch those negative-count rows were parsed as uncounted rows and collapsed `Wide Thin` to `WideThin`. After the patch, the valid source-width rows remain authoritative.

## Red-first evidence

Before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 2 failures`; both tests returned `WideThin` instead of `Wide Thin`.

## Verification

Focused after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php`

Result: `1 test files, 20 assertions, 0 failures`.

Adjacent CMap source-width subset:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php`

Result: `8 test files, 505 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-negative-count-source-width-currentbase.php`

Result: exits `0`, emits Gutenberg paragraph `<p>Wide Thin</p>`, and reports `negative_cidchar_rows_ignored=true`, `negative_cidrange_rows_ignored=true`, `valid_source_width_spans_preserved=true`, `wide_negative_count_decoys_excluded=true`, `text_runs_preserved=true`, `nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text grouping, styled-span geometry, and WordPress smoke harness. OCR, Surya/Texify/Torch, pypdfium/PDFium execution, PDF action execution, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Non-overlap

This does not repeat accepted plus-signed CMap declared counts, ordinary declared row counts, overdeclared literal-row filter boundaries, array-wrapped CMap CID decoys, high/large CID ranges, invalid/overflow CID targets, notdef char/range semantics, late CMap rows, zero-padded source-width fallback, ToUnicode row-count boundaries, `usecmap`, vertical `/W2`, indirect CIDFont widths, Type3 widths, xref repair, stream filters, annotations, forms, metadata, images, OCR/model execution, or supplied table/equation handoffs.

The bounded behavior is specifically negative declared counts before Type0 Encoding CMap CID source-width fallback.

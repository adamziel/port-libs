## Font Width Nested Encoding Decoy Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T004422Z`
Base: `dfbe19b18b25966b701cf815e7f2abbcc322da8f`

### Behavior

`PdfTextExtractor::simpleFontExplicitWidths()` now resolves `/FirstChar`, `/LastChar`, and `/Widths` from top-level simple-font dictionary entries. Nested dictionaries, including `/Encoding << /Widths [...] >>` decoys, no longer feed glyph advance metrics before same-line word-gap and styled-span bbox decisions.

This matches the native PDF parser boundary: simple-font width metrics are font dictionary entries, while Encoding dictionaries map character names and do not define the font's explicit width array. The slice stays inside searchable-PDF text extraction and does not run OCR, Surya, Texify, Torch, Python models, PDFium, or external PDF tools.

### Red-First Evidence

Before the source edit, an in-memory probe with a Type1 font containing top-level `/Widths [1000 1000 1000 1000]` plus nested `/Encoding << /Widths [250 250 250 250] ... >>` returned `AB CD` and styled bboxes `[[0,0,6,12],[24,0,30,12]]`. That exposed the nested Encoding decoy being selected as the explicit width array.

After the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 493 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php >/tmp/markerpdf-font-width-example.html`

Result: exit `0`; emitted `nested_encoding_width_decoy_excluded=true`, `nested_encoding_width_false_gap_excluded=true`, `nested_encoding_width_top_level_bboxes_preserved=true`, `nested_encoding_width_decoy_bboxes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

### Non-Overlap

This does not repeat accepted average fallback, quote/Td/Tm/TJ spacing, exact-generation width arrays, FontDescriptor `MissingWidth`, `LastChar` clipping, malformed width ranges, negative/non-finite/huge widths, Type0 `/W` or `/W2`, Type3 `FontMatrix`, CMap parsing, xref repair, object streams, AcroForm review, or stream/filter work.

### Dependency Closure

No new support component is needed. The patch reuses the existing native object parser, top-level dictionary value scanner, array resolver, and simple-font text extraction path under the current no-GPU markerPDF scope.

### Next

Continue with non-overlapping native markerPDF behavior: remaining font/CMap boundaries, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.

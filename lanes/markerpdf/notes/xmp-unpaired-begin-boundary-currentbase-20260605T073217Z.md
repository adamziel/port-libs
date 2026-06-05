# markerPDF XMP Unpaired Begin Boundary

Date: 2026-06-05 07:32 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T073217Z`

## Behavior

`PdfMetadataExtractor` now treats `<?xpacket begin ...?>` markers as usable
only when they form a complete packet before any later `begin` marker. If a
stale unpaired `begin` appears before a valid later begin/end packet, the stale
packet body is skipped and the complete current packet owns document XMP
metadata.

This prevents stale XMP roots from supplying WordPress document titles,
descriptions, dates, authors, keywords, producer metadata, or rejected-stream
review summaries. Trailing packet roots remain excluded from metadata and from
visible WordPress paragraphs.

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF metadata extraction in
  the native document-loading boundary before model/OCR stages.
- PDF/XMP parser source truth for this slice: xpacket processing instructions
  delimit XMP packet bodies, but malformed/unpaired packet markers are producer
  padding or stale bytes and must not outrank a later complete packet.

## Evidence

Red-first focused run before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnpairedBeginBoundaryCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 2 failures`.

- Expected document title `Current Unpaired Begin XMP Title`, got
  `Stale Unpaired Begin XMP Title`.
- Expected rejected-stream summary `created_at` UTC
  `2026-06-05T07:34:17Z`, got stale `2026-06-05T07:59:59Z`.

Post-fix focused run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnpairedBeginBoundaryCurrentBaseTest.php`

Result: `1 test files, 42 assertions, 0 failures`.

XMP metadata family run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `18 test files, 1576 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-unpaired-begin-boundary-currentbase.php`

Result: emitted `title_from_current_complete_packet=true`,
`packet_boundary_applied=true`, `stale_unpaired_begin_excluded=true`,
`trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Adds 2 focused PASS cases and 42 focused assertions for native XMP packet
  boundary parsing.
- Adds 1 WordPress smoke/example for stale unpaired xpacket begin exclusion.
- Adds one manifest behavior key:
  `pdfMetadataXmpUnpairedBeginBoundaryCurrentBase`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, dictionary parser, stream decoder, XMP packet candidate selection,
DOM-based XMP field extraction, `/Info` fallback decoder, and text extractor.
No Python, pypdfium, pdftext, Surya, Texify, Torch, OCR, image raster, online
service, or external PDF tool execution was run.

## Non-Overlap

This does not repeat accepted catalog metadata stream type/subtype validation,
trailing packet padding, xpacket begin/end pre-packet priority, XMP comment or
CDATA false-closing boundaries, DTD/entity rejection, UTF-16 decoding,
namespace-root skipping, self-closing or empty root fallback, typed-node
extraction, qualified-value parsing, nested qualifier parsing, language-alt
selection, generation-exact XMP references, PDF/A schema correlation, encrypted
metadata source priority, or associated-file/PieceInfo XMP review. The bounded
behavior is specifically stale unpaired `xpacket begin` recovery before a
later complete current packet.

## Next Task

Continue non-overlapping native metadata/parser work around catalog/page
metadata, fonts/CMaps, stream filters, xref repair, annotations, forms, page
geometry, image/filter metadata, and supplied-boundary table/equation handoffs
under the no-GPU markerPDF scope.

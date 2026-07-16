# markerPDF XMP packet begin/end metadata boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T065757Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF keeps searchable PDF text extraction separate from metadata/review artifacts before Markdown/WordPress conversion. In this native no-GPU PHP lane, catalog `/Metadata` XMP streams are parsed as document metadata while stream payload text stays out of visible paragraphs.
- XMP packets are bounded by `<?xpacket begin ...?>` and `<?xpacket end ...?>` processing instructions. A valid Adobe XMP-looking root before the active packet is preamble/junk for this boundary and must not become WordPress document metadata.

## Behavior

- `PdfMetadataExtractor` now searches complete xpacket begin/end packet bodies before generic XML-root fallback.
- Pre-packet Adobe XMP decoy roots are ignored when selecting promoted document XMP fields.
- Rejected XML metadata-stream review summaries are also computed from the active packet root, not from pre-packet decoy roots.
- Existing packet padding, trailing decoy packet, UTF-16, CDATA/comment, namespace, entity-rejection, typed-node, and RDF-only XMP boundaries remain green.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`

Failed before the parser change:

- `prefers active xpacket begin end root over pre packet XMP decoys`: expected `Current XPacket Root Title`, got `Pre Packet Decoy XMP Title`
- `summarizes rejected XML metadata streams from active xpacket roots only`: expected `2026-06-05T08:02:59Z`, got `2026-06-05T08:01:59Z`

## Verification

Focused packet boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`

Result: `1 test files, 76 assertions, 0 failures`.

Focused XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `16 test files, 669 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-packet-boundary-currentbase.php`

Emits `title_from_current_packet=true`, `packet_boundary_applied=true`, `pre_packet_decoy_excluded=true`, `decoy_xmp_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-packet-boundary-currentbase.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted trailing-packet padding, UTF-16 XMP decoding, CDATA/comment false-closer handling, DTD/entity rejection, namespace-root skipping, empty-root fallback, typed-node extraction, qualified-value parsing, lang-alt selection, PDF/A schema correlation, or encrypted metadata source-priority slices. The new behavior is specifically xpacket begin/end packet priority before pre-packet Adobe XMP root decoys.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet parser, metadata review summary path, text extractor, and WordPress smoke pattern. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.

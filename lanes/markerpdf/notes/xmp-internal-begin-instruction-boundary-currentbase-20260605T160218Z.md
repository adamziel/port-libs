# markerPDF XMP internal begin instruction boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T160218Z`

Accepted base: `1ec299d70fc84b468f2f246042c3fd21c99bd4eb`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable PDF text extraction and document metadata to PDF parser/PDFium-style boundaries before Markdown/WordPress conversion.
- In this native no-GPU markerPDF lane, catalog `/Metadata` XMP streams are document metadata only when the active XMP packet is bounded safely; XMP packet bytes must not leak into visible Gutenberg paragraphs.
- Existing current-base XMP slices already cover packet begin/end priority, complete-packet fallback, unpaired stale begins, non-terminal `end=` processing instructions, comments/CDATA/DTD/entity rejection, namespace wrappers, and stream-object tails.

## Implementation

- `PdfMetadataExtractor::xmpPacketContentCandidates()` now treats nested `<?xpacket begin=...?>` processing instructions inside the first bounded `xmpmeta`/`RDF` XML root as metadata content, not packet restarts.
- Stale unpaired packet starts before a later complete packet are still handled: a later `begin` outside the bounded XML root remains a new packet candidate.
- Rejected non-document XML streams use the same corrected packet root when producing redacted `xmp_summary` dates and field counts.

## Red-First Evidence

Before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInternalBeginInstructionBoundaryCurrentBaseTest.php`

Failed as expected:

- `ignores nested xpacket begin instructions inside the active XMP root`: expected `Current Internal Begin XMP Title`, got `Trailing Internal Begin Decoy XMP Title`
- `summarizes rejected XML streams from the active root around internal xpacket begins`: expected `2026-06-05T16:03:18Z`, got `2026-06-05T16:59:59Z`

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInternalBeginInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 41 assertions, 0 failures`.

Focused XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `28 test files, 1200 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-internal-begin-boundary-currentbase.php`

Emits `title_from_current_packet=true`, `packet_boundary_applied=true`, `internal_begin_instruction_ignored=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and hygiene:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpInternalBeginInstructionBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-internal-begin-boundary-currentbase.php` passed.
- `php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted XMP packet begin/end preamble priority, trailing packet padding, complete empty-packet fallback, stale unpaired begin fallback, non-terminal `end=` instruction handling, DTD/entity fail-closed behavior, namespace-wrapper skipping, CDATA/comment false-closer handling, compact RDF attribute extraction, or stream-object tail rejection. The new behavior is specifically nested `xpacket begin` processing instructions inside the active XML root before a trailing decoy packet.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet/root scanner, metadata review summary path, text extractor, and WordPress smoke pattern. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.

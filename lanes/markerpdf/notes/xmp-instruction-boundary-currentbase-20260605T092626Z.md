# XMP Instruction Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T092626Z`  
Base: `f48306bc245920a0f60018a6db3256e36339fc93`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the no-GPU native PHP scope, Catalog `/Metadata` XMP is document metadata selected before WordPress import, while packet bytes and review-only metadata stay out of visible paragraphs.
- XMP packet wrappers use a begin processing instruction and a terminal `<?xpacket end="r"?>` or `<?xpacket end="w"?>` processing instruction. Non-terminal processing instructions inside the XML tree must not close the active packet.

## Behavior

`PdfMetadataExtractor` now validates xpacket begin/end processing instructions before packet slicing:

- begin markers require a `begin=` attribute and no terminal `end=` attribute;
- end markers require `end="r"` or `end="w"` and no `begin=` attribute;
- internal processing instructions such as `<?xpacket id="review-only-boundary" end="decoy"?>` no longer terminate packet scanning;
- stale pre-packet roots and trailing decoy packets remain excluded from promoted document metadata and rejected-stream review summaries.

## Red First

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 2 failures`

Failures:

- expected `Current Instruction Boundary XMP Title`, got `Trailing Instruction Boundary Decoy Title`;
- expected rejected-summary created date `2026-06-05T09:27:26Z`, got trailing decoy date `2026-06-05T09:40:00Z`.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 43 assertions, 0 failures`

Adjacent XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInstructionBoundaryCurrentBaseTest.php`

Result: `20 test files, 1688 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-instruction-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_active_packet=true`, `nonterminal_instruction_ignored=true`, `stale_xmp_excluded=true`, `trailing_xmp_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpInstructionBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-instruction-boundary-currentbase.php` passed.

Required whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1672 -> 1674` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `1536 -> 1537` from the new WordPress smoke.
- Manifest mapped count: `730 -> 731` for `pdfXmpPacketInstructionBoundaryCurrentBase`.

## Non-Overlap

This does not repeat accepted XMP metadata reference fail-closed review, packet padding, begin/end pre-packet selection, same-prefix namespace wrapper filtering, unpaired begin recovery, UTF-16 decoding, CDATA/comment false-closer handling, DTD/entity rejection, empty/self-closing root skipping, typed-node parsing, language alternatives, qualified RDF values, PDF/A schema correlation, encrypted metadata source priority, xref metadata generation repair, outline metadata page labels, or visible text extraction slices.

The bounded behavior is only non-terminal xpacket processing instructions inside an active Catalog `/Metadata` XMP packet.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based metadata extraction, metadata review summary path, text extractor, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

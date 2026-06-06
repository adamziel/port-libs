# XMP Instruction Attribute Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T134954Z`  
Base: `380f73bd6771c85383ad351d5e11064bf53f0c34`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the no-GPU native PHP scope, Catalog `/Metadata` XMP is document metadata selected before WordPress import, while XMP packet bytes and review-only XML stay out of visible paragraphs.
- XMP packet delimiters are `xpacket` processing-instruction pseudo-attributes: real `begin=` starts a packet and terminal `end="r"` or `end="w"` closes it. Quoted strings inside unrelated attributes such as `id="not-a-delimiter begin=''"` are not packet delimiters.

## Behavior

`PdfMetadataExtractor` now parses xpacket processing-instruction pseudo-attributes before packet slicing:

- `begin` is recognized only when it is a real instruction attribute;
- terminal `end` is recognized only when a real `end` attribute has value `r` or `w`;
- quoted `begin=` and `end='w'` text inside unrelated attributes is ignored;
- stale fake-delimited roots and trailing real packets remain excluded from promoted document metadata and rejected-stream review summaries.

## Red First

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInstructionAttributeBoundaryCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 2 failures`

Failures:

- expected `Current Instruction Attribute XMP Title`, got `Stale Instruction Attribute XMP Title`;
- expected rejected-summary created date `2026-06-06T13:50:54Z`, got stale date `2026-06-06T13:59:59Z`.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInstructionAttributeBoundaryCurrentBaseTest.php`

Result: `1 test files, 42 assertions, 0 failures`

Adjacent XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `44 test files, 2819 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-instruction-attribute-boundary-currentbase.php`

Result: passed. The smoke reports `title_from_active_packet=true`, `packet_boundary_applied=true`, `quoted_begin_text_ignored=true`, `quoted_end_text_ignored=true`, `trailing_xmp_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpInstructionAttributeBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-instruction-attribute-boundary-currentbase.php` passed.

Required whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2565 -> 2567` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `2177 -> 2178` from the new WordPress smoke.
- Manifest mapped count: adds `pdfXmpInstructionAttributeBoundaryCurrentBase`.

## Non-Overlap

This does not repeat accepted XMP metadata extraction, packet padding, same-prefix namespace wrappers, unpaired begin recovery, non-terminal internal processing instructions, internal begin/end instructions inside the active root, UTF-16/declared encoding fallback, CDATA/comment false closer handling, DTD/entity rejection, empty/self-closing root skipping, typed-node parsing, language alternatives, qualified RDF values, PDF/A schema correlation, encrypted metadata source priority, xref metadata generation repair, or PageLabels integer-boundary work.

The bounded behavior is only xpacket delimiter detection that ignores quoted delimiter-looking text inside unrelated processing-instruction attributes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based metadata extraction, metadata review summary path, text extractor, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

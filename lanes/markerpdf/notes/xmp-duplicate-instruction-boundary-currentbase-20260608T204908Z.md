# XMP Duplicate Instruction Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T204908Z`  
Base: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the no-GPU native PHP scope, Catalog `/Metadata` XMP is document metadata selected before WordPress import, while XMP bytes and review-only XML stay out of visible paragraphs.
- XMP packet boundaries come from `xpacket` processing-instruction pseudo-attributes. A duplicated `begin` or `end` pseudo-attribute is ambiguous, so it must not define an active packet boundary.

## Behavior

`PdfMetadataExtractor` now rejects ambiguous XMP packet boundary instructions before packet slicing:

- duplicate `begin` pseudo-attributes do not start a packet;
- duplicate `end` pseudo-attributes do not terminate a packet, even when one value is `r` or `w`;
- stale XMP roots wrapped by duplicated pseudo-attributes are skipped before the current valid packet;
- rejected non-Metadata XML streams summarize the current valid packet while redacting text values.

## Red First

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 2 failures`

Failures:

- expected `Current Duplicate Instruction XMP Title`, got `Stale Duplicate Begin XMP Title`;
- expected rejected-summary created date `2026-06-08T20:50:08Z`, got stale date `2026-06-08T20:59:59Z`.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 46 assertions, 0 failures`

Adjacent XMP packet-instruction family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInstructionAttributeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInternalBeginInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInternalEndInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUnquotedInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`

Result: `7 test files, 329 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-duplicate-instruction-boundary-currentbase.php`

Result: passed. The smoke reports `title_from_current_packet=true`, `packet_boundary_applied=true`, `duplicate_begin_stale_packet_ignored=true`, `duplicate_end_stale_packet_ignored=true`, `trailing_xmp_excluded=true`, `rejected_review_summary_uses_current_packet=true`, `rejected_text_values_redacted=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpDuplicateInstructionBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-duplicate-instruction-boundary-currentbase.php` passed.

Required whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3479 -> 3481` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `2820 -> 2821` from the new WordPress smoke.
- Manifest mapped count unchanged; this is an additional current-base boundary behavior under existing XMP metadata coverage.

## Non-Overlap

This does not repeat accepted XMP metadata extraction, packet padding and trailing decoy exclusion, pre-packet root exclusion, unpaired begin recovery, unquoted pseudo-attribute rejection, quoted delimiter-looking text inside unrelated attributes, non-terminal internal instructions, internal begin/end instructions inside the active root, UTF-16/declared encoding fallback, malformed packet review, empty/self-closing root skipping, typed-node parsing, RDF language/list handling, PDF/A schema correlation, encrypted metadata source priority, xref metadata generation repair, or stream role `/Type` and `/Subtype` boundaries.

The bounded behavior is only duplicate `xpacket` boundary pseudo-attributes being ignored before current-packet selection and rejected-stream review summaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based metadata extraction, metadata review summary path, text extractor, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

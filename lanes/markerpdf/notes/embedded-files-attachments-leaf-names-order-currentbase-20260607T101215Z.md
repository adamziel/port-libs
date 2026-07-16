# EmbeddedFiles Attachment Leaf Names Order Boundary

Session: `port-dev-markerpdf-attachments-20260607T101215Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T101215Z`
Base accepted HEAD: `b4693a23bdd57d18c6ea6c3b1c4a37fa5ccb70a2`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF page text extraction to `pdftext.dictionary_output()`/PDFium-facing page text APIs. Embedded files are not a visible text source in that path. In this native no-GPU lane, attachments are review/preflight metadata for WordPress import and must not promote payload bytes into Gutenberg paragraphs.

PDF EmbeddedFiles are carried through a catalog `/Names /EmbeddedFiles` name tree. Name-tree leaf `/Names` arrays are key/value pairs ordered by the byte-string key; malformed current-base PDFs can present the leaf in source order such as `zulu`, `alpha`, `review`. For non-Portfolio attachments, the native review order should follow the decoded key bytes. Portfolio `/Collection` rows are intentionally excluded from this reorder because accepted Portfolio review uses the source/presentation order from the collection fixtures.

## Behavior Implemented

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now sort bounded non-Portfolio leaf `/Names` pairs by decoded key bytes before attachment summary/full embedded-file review. Duplicate keys remain stable by original source order, invalid keys are still skipped by the existing boundary checks, and Portfolio `/Collection` leaves preserve source order.

The red-first fixture has a bounded leaf:

- PDF source order: `zulu-appendix.xml`, `alpha-source.xml`, `review-summary.xml`
- Review order after fix: `alpha-source.xml`, `review-summary.xml`, `zulu-appendix.xml`

The lightweight WordPress summary omits raw attachment bytes, and fallback visible text remains only the page text.

## Verification

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentNameTreeLeafOrderBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-leaf-names-order-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentNameTreeLeafOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-leaf-names-order-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeLeafOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders EmbeddedFiles leaf Names pairs by byte key before WordPress attachment review

1 test files, 25 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php
Focused test run: 47 selected test files (root lock skipped)
...
47 test files, 3484 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-leaf-names-order-currentbase.php
```

The smoke exits 0 and emits `review_order_from_key_sort=["alpha-source.xml","review-summary.xml","zulu-appendix.xml"]`, `embedded_file_order_matches_summary=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
Exited 0 with no output.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/AF`, catalog `/AF`, FileAttachment annotation, platform `/EF` key order, checksum/Params, stream-filter stack, encryption/EFF, generation/xref, duplicate-key, Portfolio folder/schema/PieceInfo, or child `/Kids /Limits` ordering slices. The new behavior is only bounded non-Portfolio leaf `/Names` pair ordering before attachment review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, PDF string decoding, name-tree traversal, FileSpec dictionary parsing, stream-filter decoding, and WordPress smoke pattern. GPU/model OCR, Surya/Texify/Torch execution, pypdfium raster rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

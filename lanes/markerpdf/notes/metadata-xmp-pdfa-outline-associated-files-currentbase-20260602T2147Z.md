# Metadata XMP PDF/A Outline Associated Files Current Base

Implemented on isolated base `c3a3b3436899d5af64fa2dad7e137908759c83df`.

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps extracted PDF TOC rows as metadata (`marker/cleaners/toc.py::get_pdf_toc`) and writes document metadata separately from Markdown output (`marker/output.py::save_markdown`).
- The native PHP boundary remains parser-side: PDF/A root `/OutputIntents` establish the document PDF/A context, while catalog `/AF` FileSpec rows carry associated source/schema/alternative/supplement attachments through `/AFRelationship`.
- Attachment-local XMP, OutputIntent profile streams, and embedded-file payloads are review metadata only and must not be promoted into document metadata roots or visible WordPress paragraphs.

## Behavior

- `PdfMetadataExtractor` now emits `pdfa_associated_files` when a root PDF/A OutputIntent exists and catalog `/AF` rows contain associated FileSpecs.
- Existing `pdfa_associated_name_tree` behavior is preserved through the same sanitized summary helper, still limited to `Names/EmbeddedFiles` rows.
- The new current-base fixture also verifies `PdfOutlineExtractor::getNavigationReviewMetadata()` carries outline target page `/AF` attachments as target page review metadata without leaking payloads into text.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php` failed before the extractor change with expected `pdfa_associated_files`, actual `NULL` after 7 assertions.
- Focused fixed test: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php` passed with `1 test files, 58 assertions, 0 failures`.
- Related metadata/outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php` passed with `5 test files, 1009 assertions, 0 failures`.
- Metadata family: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*.php` passed with `11 test files, 1238 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-metadata-pdfa-catalog-associated-outline-currentbase.php` emitted catalog `/AF` PDF/A summary, outline target attachment metadata, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `payload_content_omitted=true`.
- Lint passed for `lanes/markerpdf/src/PdfMetadataExtractor.php`, `lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-metadata-pdfa-catalog-associated-outline-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- `phpPass`: `859 -> 860` for one new focused current-base test case.
- `wordpressScenarios`: `859 -> 860` for the new WordPress catalog `/AF` PDF/A outline smoke.
- Expected mapped semantics: `605 -> 606 / 78`, adding catalog `/AF` PDF/A associated-file summary behavior without changing the static upstream denominator.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, XMP stream decoding, OutputIntent review, catalog/page associated-file review, outline destination resolution, and text extraction boundaries. Full live markerPDF parity remains dependency-gated on Python, pdftext, pypdfium2/PDFium, OCR/model stacks, and external rendering tools.

## Non-Overlap

This does not repeat accepted `pdfa_associated_name_tree`, name-tree XMP/OutputIntent review, catalog associated-file extraction, output-intent `/AF` provenance, trailer Info/XMP date normalization, outline action thread context, or page-associated file review. The new behavior is only the PDF/A summary bridge for catalog `/AF` rows, with outline target page attachments used as review-context evidence.

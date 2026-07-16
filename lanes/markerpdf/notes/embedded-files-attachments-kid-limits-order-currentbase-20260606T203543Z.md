# markerPDF EmbeddedFiles Attachment Kid Limits Order Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T203543Z`
Session: `port-dev-markerpdf-attachments-20260606T203543Z`
Base accepted HEAD: `1a04e44c91a22f3d4217b77b07bd40823238f1c6`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF parsing to `pdftext`/PDFium before OCR/model handoff. Under the current no-GPU scope, this lane owns the native PHP parser boundary for searchable PDF objects and review-only attachment metadata.
- PDF name trees are logically ordered by name. Child `/Kids` nodes carry `/Limits` that describe their key ranges; a stale physical child-array order must not reorder WordPress EmbeddedFiles attachment review rows.
- Attachment payloads and FileSpec dictionaries remain review metadata only and are not promoted into visible WordPress paragraphs.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfAttachmentExtractor` now orders bounded catalog `/Names /EmbeddedFiles` child `/Kids` by effective child `/Limits` before collecting attachment rows.
- `PdfEmbeddedFileExtractor` applies the same ordering for full embedded-file inventories, keeping summary order and embedded-file order aligned.
- Same-lower sibling limits preserve source order.
- Child nodes without a valid dictionary stay in fail-safe source order.
- Embedded payload bytes remain omitted from attachment summaries and excluded from visible text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders EmbeddedFiles name-tree kids by Limits before WordPress attachment review
Expected: alpha-source.xml, deck-source.xml, review-summary.xml, same-lower-current.xml, same-lower-narrow.xml, zulu-appendix.xml
Actual: zulu-appendix.xml, review-summary.xml, alpha-source.xml, deck-source.xml, same-lower-current.xml, same-lower-narrow.xml
1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders EmbeddedFiles name-tree kids by Limits before WordPress attachment review
1 test files, 34 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfAttachment*Test.php' -o -name 'PdfEmbeddedFile*Test.php' -o -name 'PdfEmbeddedFiles*Test.php' \) | sort)
Focused test run: 39 selected test files (root lock skipped)
39 test files, 3014 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-kid-limits-order-currentbase.php
```

The smoke exits `0` and reports `review_order_from_limits` in alpha/deck/review/same-lower/zulu order, `embedded_file_order_matches_summary=true`, `same_lower_source_order_preserved=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2676 -> 2677`.
- `wordpressScenarios`: `2255 -> 2256`.
- `pdfAttachmentNameTreeKidLimitsOrderCurrentBaseBehaviors`: `0 -> 1`.
- `mappedPdfAttachmentNameTreeKidLimitsOrderCurrentBaseBehaviors`: `0 -> 1`.
- New focused file: `PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php` adds 1 PASS case and 34 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, parser, name-tree `/Limits` parsing, FileSpec and EmbeddedFile stream extraction, attachment summary path, embedded-file inventory path, text extractor, and WordPress smoke pattern. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted attachment EOF-bounded scanning, FileSpec duplicate-key rejection, EmbeddedFiles duplicate-name handling, direct FileSpec mirror dedupe, platform EF key selection, name-tree EF fallback order, related-file metadata, encrypted EFF suppression, stream-filter stack boundaries, object-stream/xref attachment recovery, catalog/page `/AF` review, Portfolio/PieceInfo metadata, page StructTree associated-file review, named-destination child `/Limits` ordering, or DCTDecode preview-prefix image review. The bounded behavior is only ordering valid EmbeddedFiles name-tree child `/Kids` by effective `/Limits` before attachment and embedded-file review.

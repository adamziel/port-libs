# markerPDF outline remote metadata boundary current-base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260607T042401Z`
Lane: `markerpdf`
Base accepted HEAD: `b82f9244c643b3e715f941cde65b2e86a2a3ee98`

## Source truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets PDF TOC rows from `doc.get_toc(max_depth=...)` and stores the result in `out_meta["pdf_toc"]` after `get_text_blocks(...)`, before downstream OCR/layout/model stages. Under the no-GPU markerPDF scope, this PHP lane owns the native PDF outline/action/metadata boundary that pypdfium/pdftext would otherwise provide.

PDF outline `/A /GoToR` rows and outline-local `/Metadata` streams are navigation/review metadata, not visible page text and not document-root XMP. WordPress import needs the remote action row and a payload-free hash/review of the outline-local metadata stream together so reviewers can inspect provenance without executing actions or leaking XMP payload text.

## Implemented behavior

- `PdfOutlineExtractor::getRemoteGoToActions()` now builds the existing document-outline metadata review map and passes it into the remote GoToR outline walker.
- Remote GoToR rows keep their previous shape unless the outline item has review-only document metadata context.
- When present, the row receives `outline_object` and the safe `metadata_stream_review` fields already produced by `PdfMetadataExtractor`.
- The metadata payload remains excluded from root document metadata, remote action JSON payload text, composite navigation text, and visible WordPress paragraph extraction.

## Evidence

Red-first direct run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteMetadataBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps remote outline Metadata streams review-only in document metadata
FAIL carries outline Metadata review onto remote GoToR rows without leaking payload text (lanes/markerpdf/tests/PdfOutlineRemoteMetadataBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 6
Actual: NULL

1 test files, 33 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteMetadataBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps remote outline Metadata streams review-only in document metadata
PASS carries outline Metadata review onto remote GoToR rows without leaking payload text

1 test files, 53 assertions, 0 failures
```

Adjacent outline subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 378 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-remote-metadata-boundary-currentbase.php >/tmp/markerpdf-outline-remote-metadata-smoke.html
exit=0
```

PHP lint:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineRemoteMetadataBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-remote-metadata-boundary-currentbase.php
```

## Non-overlap

This does not repeat accepted outline root/item metadata stream review, duplicate metadata key selection, navigation action review enrichment, remote GoToR extraction, destination action context, xref repair, EOF-bounded outline selection, page labels, transitions, article threads, annotations, forms, security, OCR, table, equation, or image/model behavior. The bounded behavior is specifically remote GoToR rows from `getRemoteGoToActions()` reusing the already-safe outline-local metadata review context.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, outline walker, document metadata extractor, stream-filter decoder, XMP summary redactor, text extractor, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, PDF action execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.

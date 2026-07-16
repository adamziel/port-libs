# markerPDF embedded-file page-associated boundary current-base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T021213Z`
Session: `port-dev-markerpdf-attachments-20260606T021213Z`
Base accepted HEAD: `03aa2bf4d1c2609fa6c4804de2dc061a63e86303`

## Source truth

Upstream markerPDF delegates searchable-PDF object loading and attachment handling to parser-backed PDF extraction before WordPress conversion. Under the current no-GPU markerPDF scope, this PHP lane owns native PDF parser and converter behavior only: catalog/page metadata, attachments, annotations, forms, fonts, CMaps, stream filters, xref repair, and review-only handoffs without OCR, Surya/Texify/Torch, PDFium rendering, model workers, or external PDF tools.

PDF 2.0 Associated Files may be scoped to page dictionaries through `/AF`, not only to the catalog or `/Names /EmbeddedFiles`. WordPress import review needs those page-local FileSpecs in the same embedded-file inventory used for associated-file and Portfolio review, and if the same FileSpec is also mirrored through `/EmbeddedFiles`, the row should keep its page scope rather than becoming an unscoped catalog-only attachment.

## Behavior

`PdfEmbeddedFileExtractor` now:

- walks the catalog `/Pages` tree in document order;
- collects each page dictionary's `/AF` FileSpec array into embedded-file review rows;
- annotates page associated rows with `associated_file`, `page_associated_file`, `page_number`, `page_object_id`, and `page_associated_file_index`;
- merges page-associated mirror metadata onto earlier `/EmbeddedFiles` name-tree rows that reference the same FileSpec and embedded stream.

The high-level `PdfAttachmentExtractor` already had this page `/AF` behavior; this slice closes the lower embedded-file API boundary and keeps the two attachment review surfaces consistent.

## Red first

After adding `PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php` and before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts page associated Filespec entries in EmbeddedFile review and marks name-tree mirrors
Values are not identical
Expected: 2
Actual: 1

1 test files, 1 assertions, 1 failures
```

The extractor returned only the `/EmbeddedFiles` name-tree mirror and omitted the page-only `/AF` FileSpec.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts page associated Filespec entries in EmbeddedFile review and marks name-tree mirrors

1 test files, 49 assertions, 0 failures
```

Adjacent extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 924 assertions, 0 failures
```

Attachment current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 1754 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embedded-file-page-af-boundary-currentbase.php
```

The smoke exits 0 and reports `embedded_file_count=2`, `attachment_count=2`, `filenames=["page-mirror.xml","page-only.xml"]`, `sources=["catalog_names_embedded_files","page_associated_files"]`, `page_numbers=[2,1]`, `page_mirror_source="page_af"`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat the existing high-level `PdfAttachmentExtractor` page `/AF` summary behavior, FileAttachment annotation extraction, catalog `/AF` extraction, `/EmbeddedFiles` name-tree parsing, duplicate FileSpec key fail-closed checks, encrypted EFF payload preflight, attachment stream filter stacks, xref generation repair, or CMap/font/text extraction. The bounded behavior is specifically low-level embedded-file extraction for page-scoped `/AF` FileSpecs and page-scope mirror metadata on duplicate name-tree rows.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, indirect object resolver, page-tree traversal, embedded-file stream decoder, checksum review, attachment summary preflight, and WordPress smoke renderer. OCR, Surya/Torch, Texify, pypdfium/PDFium rendering, Streamlit/FastAPI workers, model downloads, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around inherited resources, fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, security preflight, and supplied-boundary table/equation handoffs.

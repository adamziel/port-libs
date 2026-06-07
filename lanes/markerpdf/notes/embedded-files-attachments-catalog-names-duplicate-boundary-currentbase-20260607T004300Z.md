# markerpdf embedded-files attachment catalog Names duplicate boundary current-base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T004300Z`

Accepted base: `07bc98135d31956e36bc5df88c443bd479b2ac20`

## Behavior

PDF catalog `/Names` dictionaries may use escaped names such as
`/#45mbeddedFiles`, so duplicate detection has to normalize key names before a
WordPress attachment preflight trusts the document-level EmbeddedFiles name
tree. The existing attachment paths already failed closed for duplicate
name-tree node keys and duplicate FileSpec/EF keys, but a duplicate catalog
`/Names /EmbeddedFiles` owner could still let an ambiguous current/stale name
tree feed attachment review.

This patch adds the same duplicate-key boundary at the catalog `/Names`
dictionary level in both `PdfAttachmentExtractor` and
`PdfEmbeddedFileExtractor`. When duplicate `EmbeddedFiles` keys are present,
the document EmbeddedFiles name-tree traversal is suppressed. Catalog `/AF`
associated-file attachments are still preserved, so a valid associated file can
be reviewed without exposing stale or ambiguous name-tree attachments.

## Evidence

Focused run after the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCatalogNamesDuplicateEmbeddedFilesBoundaryCurrentBaseTest.php`

Result: `1 test files, 59 assertions, 0 failures`.

Adjacent attachment/embedded-file focused family run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*.php lanes/markerpdf/tests/PdfEmbeddedFile*.php`

Result: `43 test files, 3205 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-attachment-catalog-names-duplicate-boundary-currentbase.php`

Result: exits `0`; the smoke comment reports
`duplicate_embeddedfiles_name_tree_suppressed`,
`catalog_af_preserved`, `payload_bytes_omitted_from_summary`,
`executes_python_or_models`, and `executes_external_pdf_tools` with the
expected native no-runtime-execution values.

## Scope And Dependencies

No GPU/model/OCR work was run or required. The implementation reuses the
existing native PHP dictionary scanner, duplicate-key normalizer,
EmbeddedFiles name-tree collector, catalog `/AF` collector, attachment summary
path, embedded-file extraction path, and searchable text extractor. No new
support component is needed.

Non-overlap: this slice does not repeat page-level `/AF` associated-file
preflight, name-tree node duplicate key guards, FileSpec/EF duplicate guards,
xref `/Prev` attachment selection, encrypted attachment preflight, CMap
filter boundaries, annotations, forms, outlines, page labels, or model/OCR
handoffs. It only covers duplicate catalog `/Names /EmbeddedFiles` owner
boundaries before attachment name-tree traversal.

Root harness: not run - isolated micro-slice.

# markerPDF classic xref rebuild attachment preflight boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T043535Z`

Base accepted HEAD: `5b03312b7764eab59579b63cacd3703863b4e830`

## Source truth

Upstream markerPDF delegates searchable-PDF parsing to PDFium/pdftext before model-dependent work. In the native no-GPU PHP lane, classic xref recovery must therefore treat the final `startxref` as a PDF keyword boundary and repair damaged or stale classic pointers to the latest real top-level classic xref table before WordPress attachment review.

## Behavior

`PdfAttachmentExtractor` now uses token-aware classic `startxref` rebuild selection for its root catalog and xref-entry path, matching the existing text, metadata, and EmbeddedFiles extractors. The patch skips `xref`, `trailer`, and `startxref` decoys in comments, composite tokens, hex/literal strings, names, and direct object bodies before choosing a classic table.

The regression fixture keeps a stale previous classic table rooted at `stale-source.xml`, then appends the current classic table rooted at `current-source.xml` while the final `startxref` still points to the stale table. Before the source fix, `PdfAttachmentExtractor::attachmentSummary()` selected the stale 60-byte payload. After the fix, it imports the current 62-byte attachment review row, verifies the checksum, omits payload bytes from the WordPress summary, and excludes stale attachment metadata.

## Verification

Red-first focused run after adding the attachment-preflight regression and before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
Values are not identical
Expected: 62
Actual: 60
1 test files, 206 assertions, 1 failures
```

Focused run after the fix and test split:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
12 PASS cases
1 test files, 228 assertions, 0 failures
```

Attachment/xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 740 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-attachment-preflight-currentbase.php
```

Result: the smoke reports `imports_current_attachment=true`, `current_attachment_source=embedded-files-name-tree`, `current_attachment_checksum_matches=true`, `current_attachment_bytes_omitted=true`, `current_attachment_declared_size_matches=true`, `excludes_stale_attachment=true`, `attachment_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-attachment-preflight-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed in this isolated worktree.

## Non-overlap

This does not repeat accepted text/metadata/EmbeddedFiles classic xref rebuild boundaries, EOF-bounded post-garbage rejection, commented `xref` or `startxref` skipping, array/composite/name-token decoy rejection, name-offset startxref repair, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier generation repair, or runtime preflight behavior.

The bounded behavior is specifically native `PdfAttachmentExtractor` attachment preflight when a stale but valid final classic `startxref` points to a previous table and a later real classic table must provide the current WordPress attachment review root.

## Dependency closure

No new support component is needed. This reuses the existing native PHP direct-object scanner, xref table parser, xref stream parser, stream decoder, FileSpec/EmbeddedFiles attachment preflight, and WordPress smoke renderer. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU override.

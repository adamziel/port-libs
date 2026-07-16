# markerPDF classic xref rebuild embedded comment boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T004416Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T004416Z`

Base accepted HEAD: `bfc4f1bfe9ba2597b0c718fe0d3ad4e2014b4f3d`

## Source Truth

Upstream markerPDF delegates searchable-PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. The native no-GPU PHP lane owns the parser boundary before WordPress import. PDF comments begin with `%` and continue to end-of-line, so `xref` and `trailer` tokens inside comments must not select a classic xref rebuild table for text, metadata, or EmbeddedFiles attachment review.

## Behavior

`PdfEmbeddedFileExtractor` now uses token-aware classic xref and trailer scanning during damaged `startxref` rebuild, matching the existing native text and metadata boundary. Candidate `xref` and `trailer` tokens inside PDF comments, literal strings, and hex strings are skipped before selecting the trailer root for EmbeddedFiles name-tree import.

The focused fixture keeps a current classic xref table rooted at current page, XMP/Info, and EmbeddedFiles objects. It then appends decoy objects and a `% xref` comment followed by plausible xref rows, a decoy trailer, and a damaged `startxref`. Before the patch, text and metadata stayed current, but EmbeddedFiles imported `comment-xref-decoy.xml`. After the patch, the current attachment `current-comment-xref.xml` is imported and the decoy attachment is excluded.

## Verification

Red-first focused check after adding the attachment assertions and before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
FAIL skips commented xref keywords during classic rebuild before metadata root selection (lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'current-comment-xref.xml'
Actual: 'comment-xref-decoy.xml'
PASS skips commented startxref tokens before classic rebuild text metadata and attachment selection

1 test files, 78 assertions, 1 failures
```

Focused check after the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
PASS skips commented xref keywords during classic rebuild before metadata root selection
PASS skips commented startxref tokens before classic rebuild text metadata and attachment selection

1 test files, 89 assertions, 0 failures
```

Focused xref/attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 770 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-boundary-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current comment bounded page` and `Comment xref ignored`, with smoke booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `current_attachment_checksum_matches=true`, `excludes_commented_xref_page=true`, `excludes_commented_xref_metadata=true`, `excludes_commented_xref_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed in this isolated worktree.

## Non-Overlap

This does not repeat accepted text-side invalid-`startxref` classic rebuild, stale valid startxref-to-later-classic-table repair, post-EOF xref rejection, metadata-side `% xref` comment skipping, commented `startxref` rejection, stale startxref EmbeddedFiles selection, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier generation repair, or latest trailer root generation recovery.

The bounded behavior is specifically EmbeddedFiles trailer-root selection when an otherwise plausible latest classic xref table begins at an `xref` keyword inside a PDF comment before the selected damaged `startxref` token.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, trailer parser, EmbeddedFiles name-tree extractor, stream decoder, text-token extractor, metadata extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.

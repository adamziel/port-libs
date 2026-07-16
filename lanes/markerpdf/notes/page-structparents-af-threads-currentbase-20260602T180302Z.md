# markerPDF page StructParents AF Threads current-base slice

Micro-slice: `page-structparents-af-threads-currentbase-20260602T180302Z`

Accepted base: `25465d4bad4c4ed7e39379fb65c3e5365a4df98d`

## Source Truth

- Upstream markerPDF source remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py` delegates page extraction to `pdftext.extraction.dictionary_output(...)`, converts pdftext dictionaries into page blocks, preserves page number/rotation/character blocks, and reads visible page text through pypdfium in `naive_get_text`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` keeps `Page` as the page-local unit with `pnum`, `rotation`, `char_blocks`, layout/OCR/order fields, and block text separate from metadata/review state: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- Relevant PDF parser/dependency behavior comes from page dictionary keys exposed by pypdf constants: `/StructParents`, `/AF`, `/Resources`, `/B`, `/Dur`, `/Trans`, `/PieceInfo`, and `/AA` are page attributes; catalog `/Threads` is a core catalog navigation key; FileSpec dictionaries carry `/F`, `/UF`, `/EF`, `/Desc`, and `/AFRelationship`: https://raw.githubusercontent.com/py-pdf/pypdf/6.1.0/pypdf/constants.py

## Behavior Added

- `PdfPagePropertyExtractor::extractPageBoundaryMetadata()` now reuses a keyed internal page-boundary helper instead of owning the only copy of `/StructParents` ParentTree extraction.
- `PdfPagePropertyExtractor::extractPageReviewMetadata()` now composes page `/StructParents` and resolved `/StructTreeRoot /ParentTree` metadata onto page-review rows that already carry page `/AF` FileSpec checksum state, catalog `/Threads` article beads, StructTree marked-content rows, page labels, and inherited resource metadata.
- The new WordPress smoke renders only visible paragraphs while exporting a review comment with `struct_parents=7`, ParentTree MCIDs `[0,1]`, roles `["H1","P"]`, article-thread bead `21`, Source attachment checksum state, and `visible_text_excludes_review_metadata=true`.

## Red-First Evidence

Before the source change, the new focused test failed because page review rows did not carry page number/label or StructParents metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "page_number" ...
FAIL composes page StructParents ParentTree rows with associated files and article threads
Expected: 1
Actual: NULL
1 test files, 3 assertions, 1 failures
```

## Verification

- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-structparents-af-threads-currentbase.php` passed.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed: `6 test files, 1809 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-page-structparents-af-threads-currentbase.php` passed and emitted `page_label="thread-4"`, `struct_parents=7`, `parent_tree_mcids=[0,1]`, `article_thread_titles=["Thread Review Title"]`, `page_associated_checksum_matches=[true]`, and `visible_text_excludes_review_metadata=true`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused behavior tests move `621 -> 622 pass / 0 fail`.
- Mapped markerPDF semantics move `453 -> 454 / 78`.
- Added one WordPress smoke scenario for page StructParents/AF/Threads review composition.

## Non-Overlap

This does not repeat accepted standalone page `/StructParents` reading-order extraction, page `/AF` checksum review, page transition/action review, page-label number-tree parsing, page resource inheritance, or catalog article-thread fallback ordering. The bounded behavior is the composition of page `/StructParents` ParentTree review context with page-associated attachments and catalog thread beads on the same page review row.

## Dependency Closure

No new support component is needed. This slice reuses native PDF object parsing, page-tree ordering, PageLabels parsing, StructTree ParentTree/RoleMap handling, page-associated FileSpec/EmbeddedFile checksum review, catalog thread bead extraction, and visible text extraction. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

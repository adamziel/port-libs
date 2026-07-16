# markerPDF Page Thread StructTree Associated File Current Base

Micro-slice: `page-thread-structtree-associated-file-currentbase`
Session: `port-dev-markerpdf-page48-20260602T2031Z`
Base accepted HEAD: `adc2f91a9107b30c0e7e6dece7dd2846ea8fbcb8`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its `marker/pdf/extract_text.py` delegates PDF page text to pdftext/PDFium dictionaries and formats page-local spans/blocks into `Page` objects, while `marker/output.py` writes metadata separately from Markdown.
- PDF parser boundary for this slice: catalog `/Threads` bead rows are page-bound navigation metadata; page `/StructParents` binds page MCIDs through `/StructTreeRoot /ParentTree`; StructElem `/AF` FileSpec rows are provenance/review metadata. Those values must remain out of visible WordPress paragraphs and embedded payload bytes must stay review-only.

## Behavior

- `PdfPagePropertyExtractor::extractPageReviewMetadata()` now enriches each page article-thread bead row with the page-local StructTree marked-content rows it targets.
- The bead rows now expose `target_structure_mcids`, `target_structure_roles`, `target_structure_marked_content`, and deduped `target_structure_associated_files` / `target_structure_associated_file_count` when StructElem rows carry `/AF` FileSpec provenance.
- Existing page-level `/AF`, page PieceInfo, StructTree MCID rows, and article thread rows remain separate; the new fields are additive review metadata only.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS attaches StructTree associated-file provenance to page article-thread bead review rows

1 test files, 39 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-thread-structtree-associated-file-currentbase.php
PASS: emitted WordPress paragraphs for "Thread associated title visible" and "Thread associated body visible"; review metadata reported bead_target_structure_mcids=[0,1], bead_target_structure_roles=["H1","P"], bead_target_associated_filenames=["thread-struct-source.xml"], checksum_matches=true, raw_associated_content_exposed=false, visible_text_excludes_review_metadata=true.
```

Additional focused family gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS attaches StructTree associated-file provenance to page article-thread bead review rows

5 test files, 413 assertions, 0 failures
```

Syntax and lane hygiene:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php

php -l lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-thread-structtree-associated-file-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-thread-structtree-associated-file-currentbase.php

jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
PASS

git diff --check -- lanes/markerpdf
PASS
```

## Status Delta

- Focused behavior tests move `787 -> 788 pass / 0 fail`.
- Mapped markerPDF semantics move `557 -> 558 / 78`.
- Added one WordPress smoke scenario for page thread, StructTree, and StructElem associated-file provenance composition.

## Non-Overlap

This does not repeat accepted page `/AF` checksum review, page `/StructParents` ParentTree reading order, page PieceInfo/thread composition, StructElem associated-file attachment to MCID rows, article-thread bead navigation metadata, outline target thread context, or annotation StructParent association. The bounded new behavior is article-thread bead rows carrying page-local StructTree MCID and StructElem `/AF` provenance context.

## Dependency Closure

No new support component is needed. This slice reuses native PDF object parsing, page-tree traversal, StructTree ParentTree traversal, FileSpec/embedded-file review metadata, catalog article thread extraction, and visible text extraction. Full upstream parity remains gated on live pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.

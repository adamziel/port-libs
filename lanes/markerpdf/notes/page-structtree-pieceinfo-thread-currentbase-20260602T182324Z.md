# markerPDF Page StructTree PieceInfo Thread Current Base

Slice: `page-structtree-pieceinfo-thread-currentbase-20260602T182324Z`
Base accepted HEAD: `b5e63149f6bdacc97639051ac95e06ff079481ce`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::naive_get_text` and `get_text_blocks`, delegating PDF structure/text extraction to pdftext/PDFium before Marker block conversion.
- PDFium's structure-tree page path resolves page `/StructParents` through `/StructTreeRoot /ParentTree`, then binds page-local MCIDs to parent StructElem dictionaries.
- The native PHP boundary for WordPress import is review metadata only: page `/PieceInfo`, catalog `/Threads`, StructElem `/AF`, accessibility strings, and attached payload bytes must not become visible Gutenberg paragraph text.

## Implementation

- `PdfPagePropertyExtractor` now enriches fallback `page_structparents_parenttree_tagged_content` rows with the ParentTree StructElem object and the existing `PdfMetadataExtractor` StructElem review fields.
- ParentTree-bound MCID rows now carry `struct_object`, title, language, alternate/actual/expansion text, IDs, classes, and StructElem-associated FileSpec review metadata even when the StructElem omits `/Pg` and page binding exists only through `/StructParents`.
- Existing page-level composition for `/PieceInfo`, page labels, article thread beads, page associated files, resources, and visible text ordering remains unchanged.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries ParentTree StructElem review fields through page PieceInfo and article thread context
1 test files, 56 assertions, 0 failures
```

Adjacent focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php
5 test files, 968 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparents-pieceinfo-thread-currentbase.php
```

The smoke emits `visible_text_excludes_review_metadata=true`, `payload_content_omitted=true`, `mcr_struct_objects=[42,43]`, and visible paragraphs for `ParentTree title visible` and `ParentTree body visible`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structparents-pieceinfo-thread-currentbase.php
```

All reported no syntax errors.

## Non-Overlap

This does not repeat accepted page `/AF` checksum review, page StructParents ParentTree reading order, page resources/transitions/labels, direct StructTree `/Pg` MCR review, catalog `/Threads` bead navigation, outline target page review, or StructElem-associated FileSpec extraction. The new behavior is specifically carrying rich StructElem review fields onto page MCID rows when the only page binding is page `/StructParents` plus StructTreeRoot `/ParentTree`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object inventory, dictionary/array parser, StructTree review extraction, ParentTree traversal, page review metadata extractor, FileSpec review parser, text extractor, and WordPress smoke path. Full upstream parity remains gated on pdftext/PDFium execution, pypdfium2, Surya/Torch/model downloads, tabled-pdf, Texify, live Streamlit/FastAPI paths, and benchmark tooling.

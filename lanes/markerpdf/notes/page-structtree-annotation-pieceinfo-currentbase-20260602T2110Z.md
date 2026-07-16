# markerPDF Page StructTree Annotation PieceInfo Current-Base

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps native PDF page extraction delegated to `pdftext.dictionary_output` and pypdfium/PDFium-backed page text boundaries before model/layout conversion.
- PDF tagged content has two adjacent ParentTree surfaces: page `/StructParents` keys map MCID arrays for page content, while annotation singular `/StructParent` keys map object rows whose StructElem `/K` commonly contains `/Type /OBJR /Obj ...`.
- Page `/PieceInfo` is page dictionary review metadata. It can identify producer/private import state, but it is not page `/Contents` text and must not leak annotation contents, StructElem alternate/actual text, FileSpec payload bytes, or stale OBJR targets into WordPress paragraphs.

## Behavior Added

`PdfPagePropertyExtractor::extractPageReviewMetadata()` now adds `annotation_structure_parent_rows` for ordinary page annotations whose `/StructParent` resolves through `/StructTreeRoot /ParentTree` and whose StructElem `/OBJR` points back to the current page annotation object.

Each row carries:

- annotation object/index, subtype, rectangle, title/name/contents, action counts, and non-execution flags;
- nested `structure_parent` metadata from the current annotation StructParent row, including role mapping, associated FileSpec provenance, and OBJR match state;
- page `/StructParents` and page ParentTree context when present;
- page `/PieceInfo` under `page_piece_info` with `page_piece_info_review_only=true`.

Rows whose ParentTree StructElem points to a stale or detached annotation object are not promoted.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL composes page PieceInfo with current annotation StructParent review rows (lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php)
count(): Argument #1 ($value) must be of type Countable|array, null given

1 test files, 10 assertions, 1 failures
PHP Warning:  Undefined array key "annotation_structure_parent_rows" in lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php on line 57
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS composes page PieceInfo with current annotation StructParent review rows

1 test files, 60 assertions, 0 failures
```

Focused family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
25 PASS lines

9 test files, 819 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structtree-annotation-pieceinfo-currentbase.php
```

Passed. The smoke emitted a Gutenberg paragraph for `Visible annotation PieceInfo body` and review-only JSON comments for page PieceInfo plus the current OBJR-backed annotation row. It excluded `Editor note stays review only`, StructElem review text, `<wp-export>`, and `annot-piece-6` from visible text.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php

php -l lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-structtree-annotation-pieceinfo-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-structtree-annotation-pieceinfo-currentbase.php

php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status valid\n";'
lane-status valid

php -r '$p="lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "manifest valid\n";'
manifest valid

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` produced no output.

## Non-Overlap

This does not repeat accepted page `/StructParents` MCID reading order, text-markup annotation span context, annotation StructParent extraction alone, widget parent-field StructParent inheritance, page article-thread PieceInfo review, StructElem associated-file page review, annotation appearance/action rows, or FileSpec PieceInfo private-stream exclusion. The new behavior is only the page-level composition of current ordinary annotation StructParent OBJR rows with page `/PieceInfo` and page ParentTree context.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, `PdfAnnotationExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, `PdfTextExtractor`, and existing page review helpers. It does not execute Python, pdftext, pypdfium, OCR/model stacks, JavaScript, PDF actions, signatures, rasterizers, or external PDF tools.

# markerPDF Page StructParents Thread Markup Current Base

Micro-slice: `page-structparents-thread-markup-currentbase`
Session: `port-dev-markerpdf-page41pdf-20260602T1907Z`
Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; its PDF path delegates page extraction through `marker/pdf/extract_text.py` to pdftext/PDFium-style page dictionaries before Marker page/block conversion.
- Upstream markerPDF page state is page-local (`pnum`, rotation, char blocks, layout/OCR/order fields), so native PHP page review metadata should stay separate from visible block text.
- Relevant PDF parser behavior: page `/StructParents` keys map MCIDs through `/StructTreeRoot /ParentTree` array values, while annotation `/StructParent` keys map direct ParentTree object values such as StructElem rows for `/OBJR` annotation references. Catalog `/Threads` article beads are navigation/review metadata, and text-markup annotations are review metadata unless applied to supplied spans.

## Behavior

- `PdfMarkupAnnotationExtractor` now preserves text-markup annotation `/StructParent` values and carries them into supplied-span review annotations.
- `PdfPagePropertyExtractor::extractPageReviewMetadata()` now emits `text_markup_annotations` rows for page-local Highlight/Underline/Squiggly/StrikeOut annotations.
- Those rows are enriched from direct ParentTree StructElem object values, including `struct_object`, raw/mapped role, title, alternate text, actual text, ID, and classes.
- Page review rows now compose page `/StructParents` MCID rows, catalog `/Threads` article bead context, and structured text-markup annotation review metadata while visible WordPress text remains only the page content stream text.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS composes page StructParents article threads and structured text-markup annotation review

1 test files, 53 assertions, 0 failures
```

Focused page/markup family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 594 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparents-thread-markup-currentbase.php
```

The smoke emits `page_label="markup-21"`, `struct_parents=5`, ParentTree MCIDs `[0,1]`, `article_thread_titles=["Markup Article Thread"]`, `markup_struct_parent=12`, `markup_struct_object=44`, `markup_role="Span"`, and `visible_text_excludes_review_metadata=true`.

Additional checks:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structparents-thread-markup-currentbase.php
# all reported no syntax errors

php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f ok\n"; }'
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json ok
lanes/markerpdf/lane-status.json ok

git diff --check -- lanes/markerpdf
# no output
```

## Status Delta

- Focused behavior tests move `679 -> 680 pass / 0 fail`.
- Mapped markerPDF semantics move `493 -> 494 / 78`.
- Added one WordPress smoke scenario for page StructParents/thread/markup composition.

## Non-Overlap

This does not repeat accepted page `/StructParents` reading order, page `/AF` checksum review, page PieceInfo/thread/MCR composition, page resource/transition/label review, standalone text-markup QuadPoints geometry, rotated/UserUnit markup mapping, or page annotation reply-thread state. The bounded behavior is singular annotation `/StructParent` ParentTree object mapping inside page review rows while composing with existing page `/StructParents` and catalog `/Threads` context.

## Dependency Closure

No new support component is needed. This slice reuses native PDF object parsing, StructTree ParentTree traversal, PdfMetadataExtractor StructElem review rows, PdfMarkupAnnotationExtractor text-markup parsing, PdfOutlineExtractor article thread review, PdfTextExtractor visible text extraction, and the WordPress smoke path. Full upstream parity remains gated by live pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.

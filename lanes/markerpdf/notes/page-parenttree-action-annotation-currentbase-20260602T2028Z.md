# markerPDF Page ParentTree Action Annotation Current Base

Micro-slice: `page-parenttree-action-annotation-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` was verified with `git ls-remote https://github.com/sddai/markerPDF HEAD`; the local upstream clone at `/tmp/markerpdf-upstream-page47` is at that commit.
- Upstream Marker routes PDF conversion through `marker/convert.py`, calling `marker/pdf/extract_text.py::get_text_blocks()`, which delegates page text to `pdftext.extraction.dictionary_output()` and then converts page dictionaries into Marker page/block/span structures. The native PHP boundary therefore must attach annotation review data to page/span metadata without executing PDF actions or making review-only structure strings visible paragraphs.
- Relevant PDF parser behavior: current page Link annotations can carry singular `/StructParent` keys; `/StructTreeRoot /ParentTree` maps that key to a `StructElem`; the element `/K` may contain an `/OBJR` dictionary whose `/Obj` points back to the annotation. URI, GoTo, and additional-action dictionaries remain review metadata and must not execute during import.

## Implemented

- `PdfLinkAnnotationExtractor` now reuses `PdfAnnotationExtractor`'s existing annotation ParentTree review map and attaches it to promoted Link/Widget link rows by annotation object.
- `applyLinksToPages()` now carries `link_struct_parent` and `link_structure_parent` onto overlapping supplied pdftext/Marker spans.
- Hidden action annotations remain excluded from WordPress link promotion, and ParentTree titles, ActualText/Alt strings, JavaScript, and hidden action targets remain out of visible `PdfTextExtractor` output.
- Added a WordPress smoke that renders the visible safe URI link while recording the ParentTree/action review in comments only.

## Evidence

Red-first before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries ParentTree structure review through current page action link annotations
Expected: 31
Actual: NULL
1 test files, 12 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries ParentTree structure review through current page action link annotations
1 test files, 52 assertions, 0 failures
```

Adjacent family gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
5 test files, 264 assertions, 0 failures
```

Lint and smoke:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-parenttree-action-annotation-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-page-parenttree-action-annotation-currentbase.php
```

The smoke emitted `visible_link_count: 2`, `hidden_action_annotation_promoted: false`, `uri_struct_parent: 31`, `destination_struct_parent: 32`, and `visible_text_excludes_review_metadata: true`.

## Non-Overlap

This does not repeat accepted direct annotation `/StructParent` ParentTree association, page `/StructParents` MCID reading order, Widget parent-field StructParent inheritance, widget link promotion, annotation reply threads, annotation appearance review, rich-media action target review, or generic annotation action review. This slice is limited to promoted current page Link action annotations carrying existing ParentTree structure review through WordPress link row/span metadata.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page `/Annots` traversal, action review, ParentTree review extraction, link-span application, Markdown post-processing, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

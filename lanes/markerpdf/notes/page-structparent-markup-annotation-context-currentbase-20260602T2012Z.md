# markerPDF Page StructParent Markup Annotation Context Current Base

Session: `port-dev-markerpdf-page46-20260602T2012Z`
Micro-slice: `page-structparent-markup-annotation-context-currentbase`
Base accepted HEAD: `aba54dbcf7a8eaa01ed36c5fcab3cba80da2f4fa`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its `marker/pdf/extract_text.py` converts pdftext dictionary pages into page-local `Page` blocks/spans and keeps pdftext `char_blocks`, page `pnum`, bbox, and rotation as page metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` models page-local blocks, rotation, layout/order/OCR fields, and char blocks separately from rendered text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- PDFium page structure loading resolves a page `/StructParents` integer through `/StructTreeRoot /ParentTree` and builds page-local structure nodes from the returned array. This matches the existing native page review boundary for page MCID context: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7421/core/fpdfdoc/cpdf_structtree.cpp
- Annotation dictionaries use singular `/StructParent`; that key maps to the annotation entry in the structural parent tree. Public API documentation for annotation StructParent states that the key is the annotation's structural parent tree entry: https://sdk.apryse.com/api/uwp/guides/html/f1d527bf-a8e4-be3e-a8d8-0511378e1d1d.htm

## Implemented Behavior

- `PdfMarkupAnnotationExtractor::applyMarkupsToPages()` now enriches text-markup rows before applying them to supplied marker/pdftext spans.
- Supplied span `review_annotations` now carry:
  - `page_structparent_context` with page number, label, page object, page `/StructParents`, ParentTree MCIDs, ParentTree roles, and review-only flags;
  - `structure_parent` with the singular annotation `/StructParent` key, annotation object, StructElem object, RoleMap result, title, Alt, ActualText, ID, classes, current-page annotation flag, and review-only flags.
- Visible WordPress paragraph text remains sourced from page content only. Annotation `/Contents`, StructElem review strings, page labels, IDs, and accessibility strings stay out of body text.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries page StructParents and annotation StructParent context onto supplied markup review spans

1 test files, 41 assertions, 0 failures
```

Focused page/markup family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
18 PASS lines
5 test files, 475 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparent-markup-annotation-context-currentbase.php
```

The smoke emits `page_label="ctx-3"`, `page_struct_parents=9`, `page_parent_tree_mcids=[0]`, `markup_struct_parent=20`, `markup_struct_object=41`, `markup_role="Span"`, and `visible_text_excludes_review_metadata=true`.

Additional checks:

```text
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structparent-markup-annotation-context-currentbase.php
# all reported no syntax errors

php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f ok\n"; }'

git diff --check -- lanes/markerpdf
```

## Status Delta

- Focused behavior tests move `768 -> 769 pass / 0 fail`.
- Mapped markerPDF semantics move `546 -> 547 / 78`.
- Added one WordPress smoke scenario for page StructParents plus text-markup annotation StructParent context on supplied spans.

## Non-Overlap

This does not repeat accepted page `/StructParents` ParentTree reading order, page AF/thread/PieceInfo review rows, page resource/transition/label review, standalone text-markup QuadPoints geometry, rotated/UserUnit markup mapping, generic annotation StructParent rows, annotation reply threads, or text-markup rows inside page review metadata. The new behavior is specifically carrying the already parsed page and annotation structure context onto supplied marker/pdftext span-level review annotations.

## Dependency Closure

No new support component is needed. This slice reuses native PDF object parsing, page review metadata extraction, StructTree ParentTree traversal, RoleMap handling, PdfMarkupAnnotationExtractor span intersection, PdfTextExtractor visible text extraction, and the WordPress smoke path. Full upstream parity remains gated by live pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.

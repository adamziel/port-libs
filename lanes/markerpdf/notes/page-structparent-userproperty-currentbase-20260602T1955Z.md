# markerPDF page StructParent UserProperties current-base slice

Micro-slice: `page-struct-parent-user-property-currentbase`
Session: `port-dev-markerpdf-page44-20260602T1949Z`
Base accepted HEAD: `897b69532c5e798e5593546ffafd7329358413f2`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; `marker/pdf/extract_text.py` delegates page extraction to pdftext/PDFium-style page dictionaries before Marker page/block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The upstream project description documents Marker as a structured PDF-to-content pipeline that extracts text, layout, tables, forms, equations, links, and references before post-processing: https://pypi.org/project/marker-pdf/
- PDF tagged-content behavior: page `/StructParents` indexes `/StructTreeRoot /ParentTree` array values for MCID-owned structure elements. Catalog `/MarkInfo /UserProperties true` advertises `/O /UserProperties` attributes whose `/P` entries contain `/N`, `/V`, optional `/F`, and optional `/H`.

## Behavior

- `PdfPagePropertyExtractor::extractPageReviewMetadata()` now includes ParentTree-only StructElem `/A /UserProperties` rows for pages with `/StructParents`.
- The existing catalog `/MarkInfo /UserProperties true` gate is preserved; when it is false, ParentTree user properties remain suppressed.
- Review rows carry `source=page_structparents_user_properties`, `struct_parents`, `mcid`, `struct_object`, role metadata, attribute object, property name, raw/formatted value, hidden state, structure type, and title.
- Visible WordPress text remains limited to the page content stream; StructElem titles, property values, and formatted review strings are not promoted to paragraphs.

## Evidence

Red-first focused test on current base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentUserPropertyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps page StructParents ParentTree UserProperties into WordPress review metadata
Values are not identical
Expected: true
Actual: NULL
1 test files, 12 assertions, 1 failures
```

Focused behavior after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentUserPropertyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps page StructParents ParentTree UserProperties into WordPress review metadata
1 test files, 44 assertions, 0 failures
```

Focused page-structure family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentUserPropertyCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsPieceInfoThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php
7 test files, 517 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparent-userproperty-currentbase.php
```

The smoke emits `page_label="asset-44"`, `struct_parents=44`, ParentTree MCIDs `[0,1]`, user property names `["WP Block","Migration Stage","Alt Source","Confidence"]`, source `page_structparents_user_properties`, hidden-property count `1`, and `visible_text_excludes_review_metadata=true`.

Additional checks:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageStructParentUserPropertyCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structparent-userproperty-currentbase.php
# all reported no syntax errors

php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f ok\n"; }'
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json ok
lanes/markerpdf/lane-status.json ok

git diff --check -- lanes/markerpdf
# no output
```

Status delta:

- Focused behavior tests move `742 -> 743 pass / 0 fail`.
- Mapped markerPDF semantics move `529 -> 530 / 78`.
- Added one WordPress smoke scenario for page StructParents ParentTree UserProperties review.

## Non-Overlap

This does not repeat accepted page `/StructParents` reading order, page resources/transition/label review, page PieceInfo/thread/MCR composition, page associated-file review, text-markup annotation StructParent enrichment, or page annotation singular `/StructParent` association. This slice only fills ParentTree-selected StructElem `/A /UserProperties` rows that are not page-mapped through `/StructTreeRoot /K`.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object parsing, page-tree ordering, StructTree ParentTree traversal, RoleMap handling, PDF string/name decoding, and existing page review metadata assembly. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

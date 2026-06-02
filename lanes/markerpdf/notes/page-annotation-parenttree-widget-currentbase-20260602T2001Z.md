# markerPDF Page Annotation ParentTree Widget Current Base

Session: `port-dev-markerpdf-page45-20260602T2001Z`
Micro-slice: `page-annotation-parenttree-widget-currentbase`
Base accepted HEAD: `c62aa9728114b98c1a1fb9c52de68e28a30a8476`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its PDF text pipeline keeps page text extraction separate from review metadata via `marker/pdf/extract_text.py` and page/block schema boundaries.
- Relevant PDF parser behavior: Widget annotations are page annotation dictionaries; AcroForm terminal field dictionaries may carry field state and child widget references; singular `/StructParent` keys map through `/StructTreeRoot /ParentTree` to structure elements whose `/K` may contain `/Type /OBJR /Obj <annotation> R`.

## Implemented Behavior

- `PdfAnnotationExtractor` now lets page `/Widget` annotations inherit a terminal parent field `/StructParent` only when the resolved ParentTree StructElem contains an OBJR back to the current widget annotation object.
- The annotation review row records `struct_parent_source`, the parent field object, and the field chain for the inherited key.
- Stale field-only ParentTree rows are not attached to sibling/current widgets when the OBJR points at the field object instead of the widget.
- Widget structure titles, ActualText/Alt review strings, field names, and action targets remain out of visible WordPress paragraph text.

## Verification

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-parenttree-widget-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-annotation-parenttree-widget-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves widget ParentTree metadata from parent field StructParent only when OBJR matches the current page widget
1 test files, 46 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCharacteristicsCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS reviews AcroForm widget appearance characteristics without rendering captions icons or actions
PASS resolves widget ParentTree metadata from parent field StructParent only when OBJR matches the current page widget
PASS associates current page annotations with singular StructParent ParentTree entries and StructElem files
PASS resolves indirect widget link rectangles and flags at the current page annotation boundary
4 test files, 204 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-annotation-parenttree-widget-currentbase.php
```

The WordPress smoke emitted `resolved_widget_struct_parent=25`, `struct_parent_source="widget_parent_field_struct_parent"`, `field_object=20`, `structure_role="Form"`, `current_objr_matched=true`, `stale_field_parent_detached=true`, and `visible_text_excludes_review_metadata=true`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `753 -> 754` pass / `0` fail.
- Mapped markerPDF semantics move `537 -> 538 / 78`.
- WordPress scenarios move `753 -> 754`.

## Non-Overlap

This does not repeat accepted direct annotation `/StructParent` ParentTree association, page `/StructParents` MCID array reading-order extraction, page StructParents/PieceInfo/thread review, AcroForm widget appearance state review, widget link promotion, annotation reply threads, annotation appearance review, or annotation action review. This slice is limited to current page Widget annotations whose ParentTree association is carried by a terminal parent field and verified by a widget-matching OBJR.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page `/Annots` traversal, AcroForm parent-field traversal, ParentTree number-tree parsing, StructElem metadata review, action review, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

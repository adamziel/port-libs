# markerPDF Page Annotation StructParent Associated Current Base

Session: `port-dev-markerpdf-page42pdf-20260602T1922Z`
Micro-slice: `page-annotation-structparent-associated-currentbase`
Base accepted HEAD: `2f7ab5c6c7fa7a5a593e92a06a3c2a9a2e3a8f58`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py` delegates page text extraction to pdftext/PDFium-backed paths and keeps visible text extraction separate from review metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` treats pages as page-local units with text blocks separate from metadata/review state: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- Relevant PDF parser behavior: annotation dictionaries carry singular `/StructParent`; structure parent trees map those keys to structure elements; structure-element `/K` can contain `/Type /OBJR /Obj <annot> R` object references; StructElem `/AF` FileSpec rows are associated review metadata. Public ISO 32000 references list `/StructParent`, `/ParentTree`, and `/Pg` in these structure/annotation contexts: https://pdf-raku.github.io/PDF-ISO_32000-raku/

## Implemented Behavior

- `PdfAnnotationExtractor` now resolves annotation `/StructParent` keys through catalog `/StructTreeRoot /ParentTree` number trees, including `/Kids` indirection.
- Annotation rows with `/StructParent` now expose additive `struct_parent` and `structure_parent` review metadata.
- The `structure_parent` review row carries the ParentTree key, StructElem object, role-map result, title/Alt/ActualText metadata, OBJR annotation-object references, current annotation match status, and StructElem associated FileSpec provenance reused from `PdfMetadataExtractor`.
- Associated-file payload bytes, StructElem review strings, annotation `/Contents`, and link actual text remain out of visible WordPress paragraphs.

## Red-First Evidence

Before the source change, the new focused test failed because annotation rows did not carry `/StructParent` review metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "struct_parent" ...
FAIL associates current page annotations with singular StructParent ParentTree entries and StructElem files
Expected: 17
Actual: NULL
1 test files, 6 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-structparent-associated-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-annotation-structparent-associated-currentbase.php

jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json

php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS associates current page annotations with singular StructParent ParentTree entries and StructElem files
1 test files, 49 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
14 PASS lines
4 test files, 414 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-annotation-structparent-associated-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 1 selected test files (root lock skipped)
13 PASS lines
1 test files, 489 assertions, 0 failures

git diff --check -- lanes/markerpdf
```

The WordPress smoke emitted `annotation_objects=[6,7]`, `struct_parent_keys=[17,18]`, `structure_roles=["P","Link"]`, `associated_filename="annotation-source.xml"`, `associated_checksum_matches=true`, `raw_associated_content_exposed=false`, and `visible_text_excludes_review_metadata=true`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `705 -> 706` pass / `0` fail.
- Mapped markerPDF semantics move `508 -> 509 / 78`.
- WordPress scenarios move `705 -> 706`.

## Non-Overlap

This does not repeat accepted page `/StructParents` MCID reading-order extraction, page StructParents/AF/article-thread review rows, StructElem associated-file page-review rows, annotation reply threads, widget link promotion, annotation action review, annotation appearance extraction, or annotation border/color/popup/geometry review. This slice is limited to singular annotation `/StructParent` ParentTree/OBJR association and StructElem `/AF` metadata on current page annotation rows.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, annotation traversal, number-tree parsing, structure metadata extraction, FileSpec checksum review, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

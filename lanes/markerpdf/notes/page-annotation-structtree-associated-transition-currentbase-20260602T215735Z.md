# markerPDF Page Annotation StructTree Associated Transition Current Base

Session: `port-dev-markerpdf-page68-20260602T215029Z`
Micro-slice: `page-annotations-structtree-associated-transition-currentbase`
Base accepted HEAD: `46b872b82e6663ed85da04f0c1274e2577b1e5b5`

## Source Truth

- Upstream `sddai/markerPDF` is pinned at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; `marker/pdf/extract_text.py` builds page-local Marker pages from `pdftext.extraction.dictionary_output()` and pypdfium text pages, so PDF actions/attachments stay outside visible page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` models pages as block/span containers with bbox, rotation, OCR/layout/order, char blocks, and images, without an action execution path: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- Relevant PDF parser behavior: page dictionaries expose `/Dur`, `/Trans`, `/Annots`, `/AA`, and `/StructParents`; annotation dictionaries expose `/StructParent`, `/A`, `/Dest`, and `/AA`; transition dictionaries expose `/S`, `/D`, `/Dm`, `/M`, `/Di`, `/SS`, and `/B`; file specifications and embedded-file params expose `/EF`, `/AFRelationship`, and `/CheckSum`: https://pdf-raku.github.io/PDF-ISO_32000-raku/

## Implemented Behavior

- `PdfAnnotationExtractor` now computes target-page presentation context once per document and enriches only local-destination annotation action rows.
- Primary `/A` GoTo rows and annotation `/AA` GoTo rows now carry:
  - `destination_page_label`
  - `target_display_duration`
  - `target_page_transition`
  - `target_page_actions`
- Existing annotation StructParent action context remains in place, so the same action rows also retain:
  - `source_annotation_object`
  - `annotation_struct_parent`
  - compact `annotation_structure_parent`
  - `annotation_associated_file_count`
  - `annotation_associated_files`
- URI, JavaScript, and remote action rows remain review-only and do not get fake target-transition fields.
- Associated FileSpec payload bytes, StructElem titles/Alt text, annotation `/Contents`, transition action operands, URI operands, and JavaScript stay out of `PdfTextExtractor` visible text.

## Verification

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeAssociatedTransitionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries target page transition context on structured associated annotation action rows

1 test files, 58 assertions, 0 failures
```

Adjacent annotation/link/page-review gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeAssociatedTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
12 PASS lines
6 test files, 376 assertions, 0 failures
```

Lint:

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotationStructTreeAssociatedTransitionCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-structtree-associated-transition-currentbase.php
```

All passed.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annotation-structtree-associated-transition-currentbase.php
```

The smoke emitted `annotation_object=6`, `struct_parent=22`, `destination_page_label="Target 7"`, `target_transition_style="Dissolve"`, `target_page_action_safety=["review-uri","blocked-javascript"]`, `additional_action_target_transition_style="Dissolve"`, `associated_filename="annotation-transition-source.xml"`, `associated_checksum_matches=true`, `associated_payload_content_omitted=true`, `visible_text_excludes_review_metadata=true`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `870 -> 871` pass / `0` fail.
- Mapped markerPDF semantics move `614 -> 615 / 78`.
- WordPress scenarios move `870 -> 871`.

## Non-Overlap

This does not repeat accepted annotation `/StructParent` association, StructElem `/AF` extraction, page transition extraction, outline target transition propagation, promoted link target context, widget parent-field StructParent inheritance, page associated-file marked-content rows, page PieceInfo plus annotation StructParent rows, generic annotation action review, or AcroForm action FileSpec metadata. The new boundary is specifically current page annotation action rows that combine local destination target page presentation context with the triggering annotation's StructParent/StructElem/associated-file context.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page `/Annots` traversal, action review walker, destination name-tree resolver, page transition/action metadata extractor, structure ParentTree review extraction, FileSpec checksum review, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

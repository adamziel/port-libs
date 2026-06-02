# markerPDF Page Annotation StructParent Associated Action Current Base

Session: `port-dev-markerpdf-page50-20260602T2058Z`
Micro-slice: `page-annotation-structparent-associated-action-currentbase`
Base accepted HEAD: `dc17119479f92562b7d16aa7377f5088a0295935`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py` routes page text through `pdftext.extraction.dictionary_output()` into Marker page/block/span structures, and `naive_get_text()` delegates page text to pypdfium text pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` keeps pages as page-local block/span containers with bbox, rotation, char blocks, OCR/layout/order metadata, and no active PDF action execution surface: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- Relevant PDF parser behavior: page dictionaries carry `/Annots`; annotation dictionaries can carry singular `/StructParent`, primary `/A`, additional `/AA`, and action chains through `/Next`; number trees use `/Nums` and `/Kids`; StructElem `/AF` FileSpec rows are associated review metadata. Public ISO 32000 table inventory lists page `/Annots`, catalog `/StructTreeRoot`, number-tree `/Nums`, file specification `/EF`, and embedded-file `/Params` entries: https://pdf-raku.github.io/PDF-ISO_32000-raku/

## Implemented Behavior

- `PdfAnnotationExtractor` now enriches primary `actions` and `additional_actions` rows for annotations that resolve a current `/StructParent`.
- Each enriched action row gets:
  - `source_annotation_object`
  - `annotation_struct_parent`
  - compact `annotation_structure_parent`
  - `annotation_associated_file_count`
  - `annotation_associated_files` when the StructElem has `/AF` rows
- `PdfLinkAnnotationExtractor` mirrors the same enrichment after promoted Link/Widget rows reuse the annotation StructParent review map, so WordPress `link_actions_review` and `link_additional_actions_review` spans preserve the same current annotation context.
- Associated FileSpec payload bytes, annotation `/Contents`, StructElem titles/Alt text, URI operands, JavaScript strings, remote file names, and attachment payloads stay out of `PdfTextExtractor` visible text.

## Red-First Evidence

Before source edits, the new focused test failed because action rows had no annotation StructParent context:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries annotation StructParent associated files onto current action review rows
Expected: array (
  0 => 17,
  1 => 17,
)
Actual: array (
)
1 test files, 7 assertions, 1 failures
```

## Verification

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries annotation StructParent associated files onto current action review rows
1 test files, 46 assertions, 0 failures
```

Adjacent annotation/link gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
19 PASS lines
5 test files, 491 assertions, 0 failures
```

Lint, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-structparent-associated-action-currentbase.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

All passed.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annotation-structparent-associated-action-currentbase.php
```

The smoke emitted `annotation_object=6`, `struct_parent=17`, `action_safety=["review-uri","local-destination"]`, `additional_action_safety=["blocked-javascript","remote-document-review"]`, `associated_filename="annotation-action-source.xml"`, `associated_checksum_matches=true`, `associated_payload_content_omitted=true`, `span_action_struct_parent=17`, `visible_text_excludes_review_metadata=true`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `811 -> 812` pass / `0` fail.
- Mapped markerPDF semantics move `570 -> 571 / 78`.
- WordPress scenarios move `811 -> 812`.

## Non-Overlap

This does not repeat accepted annotation `/StructParent` association, StructElem `/AF` extraction, promoted link ParentTree span metadata, widget parent-field StructParent inheritance, text-markup StructParent context, page `/StructParents` MCID reading order, annotation reply threads, generic annotation action review, generic annotation appearance/popup review, or AcroForm action FileSpec metadata. The new boundary is specifically current page annotation action rows carrying their triggering annotation's StructParent/StructElem/associated-file context.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page `/Annots` traversal, action review walker, destination name-tree resolver, structure ParentTree review extraction, FileSpec checksum review, link-span application, Markdown post-processing, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

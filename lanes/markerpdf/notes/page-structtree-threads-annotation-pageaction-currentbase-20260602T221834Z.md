# markerPDF Page StructTree Threads Annotation PageAction Current Base

Micro-slice: `page-structtree-threads-annotation-pageaction-currentbase`

Base accepted HEAD: `36d3abb94323edf47dc54936168141773ec380c2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::pdftext_format_to_blocks()` and carries page blocks through `marker/schema/page.py::Page`; this native slice preserves review metadata beside those page-level import structures without executing Python, pdftext, pypdfium, or model workers.
- Relevant PDF parser behavior: page review metadata can combine catalog `/Threads` bead navigation, page `/StructParents` ParentTree MCID rows, annotation singular `/StructParent` ParentTree rows, annotation `/IRT` reply-thread state, and page/destination `/AA`, `/Dur`, and `/Trans` action metadata. Those dictionaries are metadata/action review inputs only and must not become visible WordPress text or execute.

## Implementation

- `PdfPagePropertyExtractor` now preserves sanitized annotation `actions`, `additional_actions`, and `reply_thread` rows inside `annotation_structure_parent_rows`.
- Rows now mark `annotation_actions_review_only` when action arrays exist and `target_page_action_context_review_only` when a local annotation action carries target page display duration, transition, or page additional-action context.
- Existing compact review filtering is reused so null/empty fields are omitted and previously sanitized action/file metadata remains payload-free.

## Verification

Red-first focused gate before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeThreadAnnotationActionCurrentBaseTest.php
FAIL preserves page StructTree thread annotation action context in page review rows
1 test files, 12 assertions, 1 failures
```

Passing focused gate after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeThreadAnnotationActionCurrentBaseTest.php
PASS preserves page StructTree thread annotation action context in page review rows
1 test files, 41 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structtree-thread-annotation-action-currentbase.php
```

The smoke emitted `review-4` page review metadata with StructParents `48`, article thread `Annotation action article thread`, annotation StructParents `[17,18]`, root action safety `local-destination, review-uri`, target page label `target-9`, target page actions `page_open, page_close`, and reply thread object `[7]`. Visible paragraphs were only `Thread action title visible`, `Thread action body visible`, and `Target page visible`.

## Non-Overlap

This does not repeat accepted page annotation StructParent associated-file propagation, page article-thread associated-file review, page transition/action metadata extraction, outline target page-action review, layout table annotation bundling, or annotation reply-thread extraction. The new behavior is page-level `annotation_structure_parent_rows` retaining the already built action and reply-thread review details when those rows are consumed with StructTree and catalog thread context.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, `PdfAnnotationExtractor`, `PdfActionReviewExtractor`, `PdfOutlineExtractor`, `PdfTextExtractor`, and `PdfPagePropertyExtractor`. Full upstream markerPDF runner parity remains gated by Poetry plus heavy Python dependencies and model/runtime components including pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI, and external OCR/PDF tooling.

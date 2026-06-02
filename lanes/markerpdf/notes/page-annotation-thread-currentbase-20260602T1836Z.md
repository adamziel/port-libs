# markerPDF Page Annotation Thread Current Base

Session: `port-dev-markerpdf-page39pdf-20260602T1836Z`
Micro-slice: `page-annotation-thread-currentbase`
Base accepted HEAD: `c8171d52508caddcd1c671d4d1f28bc5aa6c0960`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks` / `naive_get_text`, backed by pdftext and pypdfium text pages, while page image rendering uses PDFium with annotations disabled for extraction crops. This native slice preserves that boundary: annotation review metadata is surfaced for WordPress import, but annotation bodies, popup bodies, and detached annotation objects do not become visible paragraphs.

Relevant PDF parser behavior for this slice:

- Page `/Annots` is the current annotation membership boundary.
- Annotation `/IRT` links a reply annotation to the annotation it replies to.
- Annotation `/RT` distinguishes ordinary replies from grouped annotations.
- Annotation `/State` and `/StateModel` carry review-state metadata.
- `/IRT` targets that are not current page annotations stay object references only and must not promote detached annotation payload text.

## Implemented Behavior

- `PdfAnnotationExtractor` now emits additive `reply_thread` metadata on page annotation rows that participate in `/IRT` reply chains or carry `/State` metadata.
- Page rows now include an `annotation_threads` summary for current-page reply threads and `detached_annotation_thread_replies` for replies whose `/IRT` target is outside the page `/Annots` array.
- The metadata includes root object, reply object list, reply type labels, states, state models, detached-target flags, and explicit non-execution/non-rendering flags.
- The visible text path remains unchanged: page content imports normally, while annotation contents, popup contents, reply states, and detached target contents stay out of WordPress paragraphs.

## Evidence

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-thread-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-annotation-thread-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts page annotation reply thread state metadata without promoting detached targets
PASS keeps annotation reply thread dictionaries out of visible WordPress text

1 test files, 66 assertions, 0 failures
```

Adjacent annotation family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationThreadCurrentBaseTest.php
2 test files, 329 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annotation-thread-currentbase.php
```

The smoke emitted `review_annotation_count=4`, `thread_root_objects=[6]`, `reply_annotation_objects=[7,8]`, `reply_type_labels=["reply","group"]`, `states=["Accepted","Marked"]`, `detached_reply_objects=[9]`, `detached_reply_targets=[90]`, `visible_text_excludes_annotation_payloads=true`, and all execution/render/Python/external-tool flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `645 -> 646` pass / `0` fail.
- Mapped markerPDF semantics move `471 -> 472 / 78`.
- WordPress scenarios move `645 -> 646`.

## Non-Overlap

This does not repeat accepted catalog `/Threads` article bead reading order, outline target article-thread enrichment, page StructParents/AF/article-thread review, annotation border/color/popup metadata, selected annotation appearance import, annotation action review, text-markup QuadPoints review, rich-media annotation boundaries, or AcroForm widget review. This slice is limited to page annotation reply-thread metadata from `/IRT`, `/RT`, `/State`, and `/StateModel`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/name/string parsers, popup nesting, and text extraction boundary. Full upstream markerPDF runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, and external PDF tooling.

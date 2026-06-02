# markerPDF Annotation Popup Appearance Action Boundary

Session: `port-dev-markerpdf-annot9pdf-20260602T124610Z`
Micro-slice: `annotation-popup-appearance-action-boundary-currentbase-20260602T124610Z`
Base accepted HEAD: `f3a514f780f2b27a5b85fe755bec06c240310523`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes text extraction through `marker/pdf/extract_text.py::get_text_blocks` and `naive_get_text`, backed by `pdftext.extraction.dictionary_output` and pypdfium text pages. It also renders page images with `draw_annots=False` in `marker/pdf/images.py::render_image`. This reduced PHP slice preserves that boundary: current page annotation appearances can contribute selected visible text, but popup strings and PDF action dictionaries are review metadata only.

Relevant PDF parser behavior for this slice:

- A page's `/Annots` array is the current annotation boundary; detached annotation objects are not promoted into review rows or visible text.
- Popup annotations can be explicit `/Popup` values or reverse-linked `/Subtype /Popup /Parent` rows and should be nested under the parent annotation for review.
- Annotation `/AP /N` may be a state dictionary keyed by `/AS`; selected appearance metadata is distinct from off/stale appearance streams.
- Annotation `/A`, `/Dest`, `/AA`, and chained `/Next` actions are non-executing review metadata for WordPress import.

## Implemented Behavior

- `PdfAnnotationExtractor` now reuses `PdfActionReviewExtractor` for common page annotation rows, exposing `actions`, `additional_actions`, and `executes_actions_on_import=false`.
- Generic annotations now review local destinations, safe URI actions, chained `/Next` destinations, JavaScript `/AA` actions, and Launch `/AA` actions without executing them.
- `PdfActionReviewExtractor` now parses only the first object value for stream objects, so action review does not tokenize arbitrary appearance or media stream payload bytes.
- The focused fixture proves selected `/AP` state text remains visible through `PdfTextExtractor`, while popup text, off-state appearance streams, detached annotation appearances, and action scripts are excluded from visible WordPress paragraphs.

## Evidence

Red-first focused failure after wiring generic annotations to the action reviewer, before bounding stream-object parsing:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted ... PdfActionReviewExtractor.php on line 788
```

Passing focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
1 test files, 155 assertions, 0 failures
```

Adjacent action-review gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
3 test files, 308 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
65 test files, 4010 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-popup-appearance-action-boundary.php
```

The smoke emitted `review_annotation_count=2`, `popup_child_nested=true`, `selected_appearance_state=Review`, `selected_appearance_object=7`, `primary_action_safety=["review-uri","local-destination"]`, `additional_action_safety=["review-uri","blocked-javascript"]`, `direct_dest_view_mode=FitH`, `launch_action_review_only=true`, `popup_text_excluded_from_visible_text=true`, `stale_appearance_excluded_from_visible_text=true`, `action_scripts_excluded_from_visible_text=true`, and all execution/render flags false.

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-annotation-popup-appearance-action-boundary.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests move `501 -> 502`.
- Mapped markerPDF semantics move `349 -> 350 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted annotation border/color/opacity/popup-only metadata, selected annotation appearance resource/BBox import, link/text-markup destination and `/AA` action application, rich-media current annotation action target boundaries, Screen action `/AN` detached target boundaries, Sound/Movie/Rendition popup media review, or AcroForm widget appearance/action review. The new behavior is the generic page annotation boundary where popup nesting, selected `/AP`, `/Dest`, `/A`, `/AA`, and stale detached annotation exclusion are reviewed together through `PdfAnnotationExtractor`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, existing action reviewer, destination name-tree resolver, annotation appearance summary path, text extractor appearance import boundary, and WordPress review metadata path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

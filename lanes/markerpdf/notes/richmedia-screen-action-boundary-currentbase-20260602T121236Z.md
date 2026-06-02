# markerPDF Screen Action Target Boundary

Session: `port-dev-markerpdf-rich7pdf-20260602T121236Z`
Micro-slice: `richmedia-screen-action-boundary-currentbase-20260602T121236Z`
Base accepted HEAD: `3373607208163bd1c123d073b17b4959e3e228a4`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` treats PDF import as extracted page content plus review metadata. It does not execute PDF viewer actions, JavaScript, media players, Python models, pypdfium, pdftext, or external PDF tools during the native PHP import boundary.

Relevant PDF parser behavior for this slice: `/Screen` annotations can carry `/Movie`, `/Rendition`, `/URI`, `/Launch`, and `/JavaScript` actions. Action dictionaries may name a target annotation object, but that target is not itself a current page annotation unless it appears in the page's top-level `/Annots` array. Detached target annotations should therefore stay as review-only object references and must not contribute stale Movie dictionaries, media file names, appearance text, or executable behavior to the current page import.

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now builds a per-page top-level annotation object map before reviewing media annotations.
- Movie action rows still preserve `target_annotation_object`, `title`, operation, safety, and non-execution flags.
- Movie target annotation dictionaries are dereferenced for movie details only when the target object is one of the current page's top-level annotations.
- Rendition and RichMediaExecute action rows now also expose `target_annotation_is_page_annotation` so review UIs can distinguish current-page targets from detached/stale targets.
- Added `wordpress-pdf-richmedia-screen-action-boundary.php` as the WordPress smoke.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
FAIL keeps detached screen action targets out of current annotation media review rows
Expected: false
Actual: NULL
1 test files, 304 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 326 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
64 test files, 3836 assertions, 0 failures
```

Changed PHP syntax checks:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-action-boundary.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-action-boundary.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-action-boundary.php
```

The smoke emitted `review_annotation_count=1`, `review_action_count=4`, `target_annotation_object=50`, `target_annotation_is_page_annotation=false`, `stale_target_movie_not_promoted=true`, `stale_media_file_excluded=true`, `current_uri_reviewed=true`, `appearance_and_stale_text_excluded=true`, and all execution flags false.

Lane metadata and whitespace checks:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . "\n"); exit(1); } } echo "valid json\n";'
valid json

git diff --check -- lanes/markerpdf
```

## Status Delta

- Behavior tests move `494 -> 495`.
- Mapped markerPDF semantics move `342 -> 343 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted rich-media popup/action chains, RichMedia action target boundaries, Screen playback `/P`/`SP` policy dictionaries, Sound/Movie/Rendition action detail rows for current page annotations, page transition/action rows, annotation border/popup review, JavaScript action safety inventory, or visible media payload exclusion. The new behavior is limited to Screen action target annotation membership checks before Movie target dictionary enrichment.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, string/name/number/bool decoders, existing action-chain walker, and visible text extraction boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

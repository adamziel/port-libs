# markerPDF RichMedia Screen Playback Policy

Session: `port-dev-markerpdf-richpdf-20260602T105527Z`
Micro-slice: `richmedia-screen-sound-action-review-currentbase-20260602T105527Z`
Base accepted HEAD: `32c83110c4ddf570f9851fa840f0f100432adb83`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF parsing and document conversion as extracted blocks plus review metadata; it does not execute PDF viewer actions, media players, JavaScript, Python models, pypdfium, or external tools during import.

Relevant PDF parser behavior for this slice: Screen/Rendition actions can point to a Rendition dictionary whose `/P` media play parameters and `/SP` media screen parameters carry `/MH` must-honor and `/BE` best-effort dictionaries. Those playback/display constraints are review metadata for WordPress import. They do not make embedded media payload bytes, appearance stream text, or viewer actions visible/executable.

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now records Rendition `/P` play-parameter and `/SP` screen-parameter dictionaries.
- The review rows preserve `/MH` and `/BE` dictionary object numbers, top-level keys, booleans, numbers, names, strings, and simple number/string arrays.
- Existing Rendition action rows continue to report target annotations, operation labels, media clips, file names, and safety flags.
- Screen appearance streams and embedded media streams remain excluded from native visible text extraction.
- Added `wordpress-pdf-richmedia-screen-playback-policy.php` as the WordPress smoke.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
FAIL reviews screen rendition play and screen parameter dictionaries without executing media
Expected: 15
Actual: NULL
1 test files, 220 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 248 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
62 test files, 3420 assertions, 0 failures
```

Changed PHP syntax checks:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-playback-policy.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-playback-policy.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-playback-policy.php
```

The smoke emitted `review_annotation_count=1`, `play_must_honor_controls=true`, `play_best_effort_mode=Once`, `screen_must_honor_window=Window`, `screen_best_effort_window=FullScreen`, `media_clip_type=audio/mpeg`, `media_payload_text_excluded=true`, and all execution flags false.

Metadata and lane whitespace checks:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . "\n"); exit(1); } } echo "valid json\n";'
valid json

git diff --check -- lanes/markerpdf
```

## Status Delta

- Behavior tests move `475 -> 476`.
- Mapped markerPDF semantics move `325 -> 326 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted rich-media popup/action chains, Sound/Movie action detail rows, Rendition operation/media-clip rows, current page annotation target boundaries, page transition/action rows, annotation border/popup review, or visible media payload exclusion. The new behavior is limited to Screen/Rendition `/P` and `/SP` `/MH`/`/BE` playback policy dictionaries on existing non-executing media action review rows.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, string/name/number/bool decoders, existing Rendition action review rows, and visible text extraction boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

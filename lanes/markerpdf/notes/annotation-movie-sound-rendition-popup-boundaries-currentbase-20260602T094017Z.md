# markerPDF Movie Sound Rendition Popup Boundaries

Session: `port-dev-markerpdf-movie30-20260602T094017Z`
Micro-slice: `annotation-movie-sound-rendition-popup-boundaries-currentbase-20260602T094017Z`
Base accepted HEAD: `f50b457cdfd12d887c5fc62e07c8d2bad733a41d`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF parsing, metadata, image rendering, and model/layout conversion as separate boundaries. The native PHP lane follows the same import rule for interactive media: collect review metadata but do not execute PDF actions, media players, JavaScript, Python models, pypdfium, or external PDF tools.

Relevant PDF parser behavior for this slice: Movie, Sound, Screen/Rendition annotations and actions are dictionaries with target annotation, playback, media-clip, operation, and popup fields. `/Popup` annotations may be linked directly from the media annotation or reverse-linked by `/Parent`. Those fields are review metadata, not visible page text and not executable import behavior.

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now expands `/S /Movie` action rows with target annotation object, title, operation, and the target annotation's movie dictionary metadata.
- `/S /Sound` action rows now include sound stream metadata plus `/Volume`, `/Synchronous`, `/Repeat`, and `/Mix` playback flags.
- `/S /Rendition` action rows now include `/AN` target annotation, `/OP` operation labels, structured rendition dictionaries, media clip `/CT`, `/D` Filespec names, and `/Alt` text arrays.
- Movie, Sound, and Screen/Rendition annotations preserve direct and reverse-linked popup metadata without emitting popup annotations as media rows.
- Media appearance streams, sound payload streams, and popup contents stay out of visible WordPress paragraph text.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
FAIL reviews movie sound and rendition action popup boundaries without executing media
Expected: 5
Actual: NULL
1 test files, 144 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 180 assertions, 0 failures
```

Lane-only markerPDF gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
61 test files, 3097 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-movie-sound-rendition-popup-boundaries.php
```

The smoke emitted `review_annotation_count=3`, `action_types=["Movie","Sound","Rendition"]`, `movie_action_target=5`, `sound_repeat=true`, `rendition_operation_label=play`, `rendition_media_clip_type=video/mp4`, three popup review rows, and `media_payload_text_excluded=true`.

## Status Delta

- Behavior tests move `456 -> 457`.
- Mapped markerPDF semantics move `308 -> 309 / 78`.

## Non-Overlap

This does not repeat accepted rich-media `/A`/`AA` chained action safety rows, standalone Screen/RichMedia review metadata, standalone Sound/Movie annotation dictionaries, page annotation border/color/popup rows, text-markup popup rows, JavaScript safety inventory, or page transition/action metadata. This slice is limited to structured Movie/Sound/Rendition action details plus popup boundaries on the media annotation extractor.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, string/name decoding, Filespec extraction, existing popup linking, and visible text extraction boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

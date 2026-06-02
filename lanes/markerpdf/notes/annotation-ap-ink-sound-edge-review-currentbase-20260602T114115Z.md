# markerPDF Annotation AP Ink Sound Edge Review

Session: `port-dev-markerpdf-annots5pdf-20260602T114115Z`
Micro-slice: `annotation-ap-ink-sound-edge-review-currentbase-20260602T114115Z`
Base accepted HEAD: `dd7c0dd1c605f36e3ddc2f37784f7912f6eee524`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction at the pdftext/pypdfium boundary and image extraction at a PDFium render boundary with annotations disabled for crops. This PHP slice keeps annotation appearance streams and sound streams as native review metadata for WordPress import. It does not execute PDF actions, play media, render annotation appearances, invoke Python/model workers, or call external PDF tools.

Relevant PDF parser behavior for this slice:

- Annotation `/AP` dictionaries can hold `/N`, `/R`, and `/D` entries. `/N` may be a state dictionary keyed by appearance state names, with `/AS` selecting the current normal appearance.
- Ink annotations keep stroke paths in `/InkList`, while appearance streams are separate Form XObject review metadata.
- Sound annotations carry a `/Sound` stream dictionary with sample rate `/R`, channel count `/C`, bits per sample `/B`, encoding `/E`, optional compression `/CO`, `/Filter`, and `/Length`. Those stream bytes are not visible WordPress text and are not played on import.

## Implemented Behavior

- `PdfAnnotationExtractor` now emits bounded `/AP` review metadata on common annotation rows.
- `/AP /N` state dictionaries preserve ordered state names, selected `/AS` state, selected Form XObject object number, `/Subtype`, `/BBox`, `/Matrix`, `/Resources` top-level keys, `/Length`, and `/Filter`.
- Direct `/AP /R` and `/AP /D` stream entries are summarized as review-only appearance streams.
- Sound annotations now expose stream object, icon name, sample rate, channels, bits per sample, encoding, compression, declared payload length, filters, and explicit non-playback flags.
- Ink annotation geometry remains the existing source of path metadata; the new behavior adds AP-state review metadata around those paths.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
FAIL reviews ink annotation appearance dictionaries and sound stream metadata without playback
Expected: 'BlueStroke'
Actual: NULL
1 test files, 96 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
1 test files, 119 assertions, 0 failures
```

Adjacent annotation/media/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 929 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-ap-ink-sound-review.php
```

The smoke emitted `review_annotation_count=2`, `ink_paths=2`, `ink_selected_ap_state=BlueStroke`, `ink_selected_ap_object=7`, `sound_stream_object=9`, `sound_sample_rate=44100`, `sound_filters=["ASCIIHexDecode"]`, `sound_payload_text_excluded=true`, and all execution/render flags false.

Changed PHP syntax checks:

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAnnotationExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-annotation-ap-ink-sound-review.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-annotation-ap-ink-sound-review.php
```

Metadata and whitespace checks:

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Both commands passed with no output.

## Status Delta

- Behavior tests move `488 -> 489`.
- Mapped markerPDF semantics move `336 -> 337 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted RichMedia GoToE embedded attachment actions, RichMedia Screen playback policy dictionaries, standalone Movie/Sound/Rendition action popup rows, text-markup popup metadata, annotation border/color/opacity/popup metadata, or page annotation geometry. The new behavior is limited to common annotation `/AP` review summaries and Sound stream metadata on `PdfAnnotationExtractor`, with an Ink annotation proving selected-state AP handling around existing stroke paths.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, string/name/number decoders, existing stream-reference boundaries, existing text extraction exclusion behavior, and WordPress review metadata path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

# markerPDF RichMedia Embedded Action Media Current Base

Session: `port-dev-markerpdf-rich13pdf-20260602T1342Z`
Micro-slice: `richmedia-embedded-action-media-currentbase-20260602T1342Z`
Base accepted HEAD: `12bbc32f4b8bbc9c171e9e7744e7bceec9580f73`

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text extraction through page text/dictionary output boundaries in `marker/pdf/extract_text.py`; this slice preserves that import contract by producing review metadata while not executing PDF viewer media, JavaScript, Python/model workers, pypdfium, pdftext, or external PDF tools: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF rich-media structure uses annotation-scoped RichMediaContent and RichMediaSettings dictionaries, with content configurations and instances pointing to media assets. Source references: https://pdf-issues.pdfa.org/32000-2-2020/clause13.html and https://api.itextpdf.com/iText5/java/5.5.10/com/itextpdf/text/pdf/richmedia/RichMediaConfiguration.html
- RichMediaExecute actions identify a target annotation and can target a RichMediaInstance, carry a command, and carry typed command arguments; RichMediaParams carries FlashVars/settings/binding metadata. Source references: https://api.itextpdf.com/iText5/java/5.5.12/com/itextpdf/text/pdf/richmedia/RichMediaExecuteAction.html and https://api.itextpdf.com/iText5/java/5.5.13/com/itextpdf/text/pdf/richmedia/RichMediaParams.html

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now treats `/TA` as a RichMediaExecute target annotation alongside the accepted `/AN` key.
- RichMediaExecute review rows now handle direct PDF 2.0-style `/C` command and `/A` arguments, while preserving the accepted legacy `/CMD << /C ... /A ... >>` command dictionary form.
- RichMediaExecute `/TI` target instance references now emit `target_instance_object` plus a review-only `target_instance` summary with subtype, target asset Filespec metadata, embedded-file object ids, MIME types, RichMediaParams binding, FlashVars, settings, and cue-point scalar arrays.
- The action-chain file-name inventory follows `/TI -> /Asset` only through the current annotation's action context, so stale catalog EmbeddedFiles are not promoted into the current page review row.
- Added `wordpress-pdf-richmedia-embedded-action-media-currentbase.php` to prove the WordPress path sees review metadata while media streams, controller script streams, action JavaScript, appearance text, and stale catalog media payloads stay out of visible paragraphs.

## Evidence

Red-first focused failure before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
FAIL reviews rich media execute target instances command arguments and embedded media without execution
Expected: 5
Actual: NULL
1 test files, 400 assertions, 1 failures
```

Focused pass after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 434 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
2 test files, 993 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-embedded-action-media-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-richmedia-embedded-action-media-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-embedded-action-media-currentbase.php
```

The smoke emitted `review_annotation_count=1`, `review_action_count=3`, `target_annotation_object=5`, `target_instance_object=41`, `target_instance_asset=action-video.mp4`, `target_instance_mime_types=["video/mp4"]`, `command_arguments=["intro",12,true]`, `legacy_command_arguments=outro`, `flash_vars=src=action-video.mp4&autoplay=false`, `cue_points=["intro",12,true]`, `stale_catalog_media_excluded=true`, `payload_text_excluded=true`, and all execution flags false.

## Status Delta

- Behavior tests move `510 -> 511`.
- Mapped markerPDF semantics move `358 -> 359 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted RichMedia GoToE attachment actions, Screen/Rendition `/OP` and `/JS` review metadata, playback `/P`/`/SP` policy dictionaries, detached Screen/Movie target boundaries, movie/sound/rendition popup rows, current annotation action target boundaries, generic annotation action review, or visible media payload exclusion. The new behavior is limited to RichMediaExecute `/TA`, `/TI`, direct `/C`, `/A`, legacy `/CMD /A`, and target-instance media/params review.

## Dependency Closure

No new support component is needed. The implementation reuses the native PDF object scanner, dictionary/array tokenizer, page annotation traversal, Filespec/embedded-file metadata decoder, scalar string/number/bool decoders, action-chain walker, and visible text extraction boundary. Full upstream markerPDF Python/pdftext/pypdfium/Surya/Texify/model benchmark parity remains dependency-gated and was not executed.

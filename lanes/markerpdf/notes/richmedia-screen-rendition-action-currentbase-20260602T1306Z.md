# markerPDF Screen Rendition Action Current Base

Session: `port-dev-markerpdf-rich10pdf-20260602T1306Z`
Micro-slice: `richmedia-screen-rendition-action-currentbase-20260602T1306Z`
Base accepted HEAD: `f11dfede7c9cc6cec4442399e4597689bf6e8f1d`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` treats PDF import as extracted page content plus review metadata. The native PHP lane must not execute PDF viewer actions, JavaScript, media players, Python models, pypdfium, pdftext, or external PDF tools during WordPress import.

Relevant PDF parser behavior for this slice comes from ISO 32000 rendition actions: a rendition action dictionary carries `/S /Rendition` plus `/R`, `/AN`, `/OP`, and optional `/JS`; operation `4` is play-or-resume, while stop/pause/resume operations can apply to the current rendition already associated with the target screen annotation. The review importer should therefore expose operation, target annotation, current-associated scope, and script metadata without executing the script or media.

Primary source references used: Adobe PDF 32000-1 (`PDF_ISO_32000-1.pdf`) and PDF action parser summaries for rendition action `/OP` and `/JS` entries:

- https://developer.adobe.com/document-services/docs/assets/35e4369068f86065372c18787171a17e/PDF_ISO_32000-1.pdf
- https://pkg.go.dev/seehuhn.de/go/pdf@v0.7.1/action
- https://pdf-raku.github.io/PDF-ISO_32000-raku/

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now labels Rendition action `/OP 4` as `play_or_resume`.
- Rendition action rows with `/R` now report `rendition_scope=specified-rendition`.
- Rendition stop/pause/resume rows without `/R` now report `rendition_scope=current-associated-rendition` and `uses_current_rendition=true`.
- Rendition action `/JS` strings are recorded as non-executing review metadata with preview, hash, byte count, and truncation state.
- Added `wordpress-pdf-richmedia-screen-rendition-action-currentbase.php` to show the WordPress review path and prove appearance streams, embedded media payloads, and script text do not become visible paragraphs.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
FAIL reviews current-base screen rendition action operations and JavaScript without executing media
Expected: 'play_or_resume'
Actual: 'unknown'
1 test files, 343 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 383 assertions, 0 failures
```

Focused adjacent lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
2 test files, 942 assertions, 0 failures
```

Changed PHP syntax checks:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-rendition-action-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-rendition-action-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-rendition-action-currentbase.php
```

The smoke emitted `review_annotation_count=1`, `review_action_count=4`, `operation_labels=["play_or_resume","pause","resume","stop"]`, `current_scope_events=["PO","PV","PI"]`, `script_hash_count=3`, `play_or_resume_label_supported=true`, `current_rendition_actions_have_no_rendition_dictionary=true`, `stale_rendition_file_excluded=true`, `appearance_payload_and_script_text_excluded=true`, and all execution flags false.

## Status Delta

- Behavior tests move `504 -> 505`.
- Mapped markerPDF semantics move `352 -> 353 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted rich-media popup/action chains, Sound/Movie/Rendition media clip rows, Screen playback `/P`/`/SP` policy dictionaries, detached Screen action target boundaries, generic annotation action review, JavaScript action-chain inventory, or visible media payload exclusion. The new behavior is limited to Rendition action operation `4`, no-`/R` current-associated stop/pause/resume rows, and Rendition action `/JS` review metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, dictionary/array tokenizer, string/name/number decoders, existing Rendition action review rows, and visible text extraction boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

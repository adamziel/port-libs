# richmedia-screen-selector-rendition-currentbase

Micro-slice: `annotation-richmedia-screen-rendition-review-currentbase`

## Source Truth

- Upstream markerPDF pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF text parsing to `pdftext` and PDFium rather than executing annotation actions: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- Upstream image rendering calls PDFium with annotation drawing disabled, so Screen/Rendition media belongs in review metadata rather than rendered or played output: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py>
- PDF Screen annotations can carry Rendition actions. Rendition selector dictionaries (`/S /SR`) choose among alternate media renditions via `/R`, while `/MH` and `/BE` dictionaries describe must-honor and best-effort media criteria. Media renditions (`/S /MR`) point to media clips (`/S /MCD`) whose `/D` operand can be a FileSpec with embedded-file stream metadata.

## Behavior

- `PdfRichMediaAnnotationExtractor` now reviews selector renditions recursively with bounded cycle protection.
- Rendition dictionaries now expose selector alternatives under `renditions`, top-level `/MH` and `/BE` criteria under `must_honor` and `best_effort`, and recursive `file_names`.
- Media clip dictionaries now retain FileSpec review metadata for `/D`, including embedded-file streams, content hashes, declared size, checksum match state, dates, MIME type, and AF relationship.
- Annotation/action `file_names` now include filenames found through action rendition details, not just directly adjacent annotation bodies.

## WordPress Smoke

Added `lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-selector-rendition-currentbase.php`.

The smoke imports one Screen annotation with a Rendition action pointing at a selector rendition, a primary embedded `video/mp4` FileSpec, a fallback string media file, and a stale detached FileSpec. It emits Gutenberg paragraph/list blocks and a JSON audit comment while proving:

- the selector object, criteria keys, alternatives, embedded stream checksum, and fallback clip are review metadata;
- media and JavaScript execution remain false;
- appearance stream text, embedded media payload text, and stale detached file text stay out of visible WordPress paragraphs.

## Evidence

Red/baseline before the extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 470 assertions, 0 failures
```

Focused result after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 550 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-selector-rendition-currentbase.php
passed; emitted markerpdf-pdf-richmedia-screen-selector-rendition-currentbase audit metadata, Article Body paragraph, and primary/fallback rendition list rows.
```

Syntax and lane checks:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-screen-selector-rendition-currentbase.php
git diff --check -- lanes/markerpdf
All passed.
```

Status delta: focused `PdfRichMediaAnnotationExtractorTest.php` assertions move `470 -> 550` (`+80`); `phpPass` and `wordpressScenarios` move `837 -> 838`.

## Non-Overlap

This does not repeat accepted Screen/Rendition operation-label and JavaScript review, `/P` and `/SP` playback policy dictionaries, Sound/Movie annotation review, popup/nested action chains, detached Screen action-target boundaries, RichMediaExecute target-instance media review, JSON hidden/visible constraints, object-stream/xref repair, table OCR, or security/DSS slices.

The new boundary is specifically Screen Rendition selector review: `/S /SR` alternative renditions, rendition-level `/MH` and `/BE` media criteria, and `/S /MCD` media clip FileSpec streams.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object scanner, annotation page walker, action-chain reviewer, FileSpec/EmbeddedFile metadata extractor, text extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by live Python dependencies such as `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, model downloads, and external OCR/rendering helpers.

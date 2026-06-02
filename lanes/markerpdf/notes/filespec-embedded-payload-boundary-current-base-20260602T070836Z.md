# markerPDF FileSpec Embedded Payload Boundary

Session: `port-dev-markerpdf-filesafe8-20260602T070836Z`
Micro-slice: `markerpdf-filespec-embedded-payload-boundary-current-base-20260602T070836Z`
Base accepted HEAD: `ddef7701b1c5b9d5eb284eb986e7477f2ebab827`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains visible PDF text from pdfium/pdftext page text extraction in `marker/pdf/extract_text.py`; the native PHP fallback must preserve that page-text boundary and must not turn PDF attachment payload streams into visible WordPress paragraphs.

Relevant upstream source:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py

PDF FileSpec dictionaries use `/EF` to point at embedded-file streams. Those streams are attachment payloads for review/import metadata, not page content streams, even when a producer omits the stream dictionary `/Type /EmbeddedFile` marker.

## Native Behavior Added

`PdfTextExtractor` now collects object numbers reached through FileSpec `/EF` dictionaries and excludes those stream objects from fallback all-stream text scanning. This keeps malformed or producer-lax attachment streams out of visible text extraction while preserving `PdfEmbeddedFileExtractor` metadata and byte extraction.

The red-first check before the fix leaked attachment bytes as visible text:

```text
Filespec Payload Leak
```

After the fix, the same PDF still yields one attachment row from `PdfEmbeddedFileExtractor`, but `PdfTextExtractor::extractPlainText()` returns an empty string for the payload-only fixture.

## Evidence

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
1 test files, 125 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
58 test files, 2481 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embedded-files-import.php
```

The smoke emits `attachment_count=2`, `excluded_filespec_ef_payload_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false` while rendering only `Visible Attachment Review` as paragraph text.

Syntax checks passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-embedded-files-import.php
```

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, dictionary parsing, FileSpec `/EF` attachment resolution, fallback stream decoding, and the existing WordPress embedded-file smoke path. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

# markerpdf metadata xref-stream trailer edge current base

Slice: `markerpdf-metadata-trailer-xmp-info-edge-current-base-20260602T072927Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` ultimately relies on pdftext/PDFium document parsing before WordPress-safe metadata review. Native PHP must therefore resolve document catalog and metadata from the current PDF trailer, not just fallback object scans.
- PDF 1.5 xref stream dictionaries carry trailer keys such as `/Root`, `/Info`, `/ID`, and `/Encrypt`. This slice keeps metadata extraction aligned with the existing native xref-stream text parser by treating the latest `startxref` xref-stream dictionary as the current trailer.

Implemented behavior:

- `PdfMetadataExtractor` now resolves the trailer dictionary at the latest `startxref` offset.
- If the latest offset points to an xref stream object, the xref stream dictionary supplies `/Root`, `/Info`, and `/ID` metadata before stale textual trailers.
- Textual `trailer <<...>>` parsing remains the fallback for older PDFs, with a last-xref-stream fallback for trailerless static fixtures.

Focused evidence:

- Baseline before patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `1 test files, 144 assertions, 0 failures`.
- Added regression: stale textual trailer points to stale `/Info` and `/ID`; latest xref stream points to current `/Root`, `/Info`, and `/ID`; XMP title, Info author/producer/date fallback, current trailer ID fingerprint, and visible body text all resolve from the current stream-trailer boundary.
- Focused post-patch command: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  - Result: `1 test files, 161 assertions, 0 failures`.
- Syntax check: `php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-metadata-import.php`
  - Result: no syntax errors in all three changed PHP files.
- JSON metadata check: `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON metadata files validated\n";'`
  - Result: `markerpdf JSON metadata files validated`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-xref-stream-metadata-import.php`
  - Result: smoke metadata emitted `source=["xmp","info","trailer_id"]`, `current_xref_stream_info_selected=true`, `current_xref_stream_id_selected=true`, `stale_textual_trailer_excluded=true`, and `metadata_not_visible_text=true`.
- Diff hygiene: `git diff --check -- lanes/markerpdf`
  - Result: passed.
- Supervisor full-lane gate after applying on current integration base `2a254f017`: `php tools/run-tests.php lanes/markerpdf/tests`
  - Result: `59 test files, 2580 assertions, 0 failures`.

WordPress scenario:

- Added `examples/wordpress-pdf-xref-stream-metadata-import.php` to show a PDF import where current xref-stream metadata is emitted as review-only document metadata while stale textual trailer metadata and XMP packet text are excluded from Gutenberg paragraph content.

Dependency closure:

- No new support component is needed. This reuses the existing native PDF object/dictionary parsing and Flate stream decoding already present in the markerpdf lane; upstream Python models, PDFium, pdftext, and external PDF tools remain dependency-gated and are not executed.

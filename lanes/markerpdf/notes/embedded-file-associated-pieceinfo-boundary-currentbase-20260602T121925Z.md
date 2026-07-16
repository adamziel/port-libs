# Embedded File Associated PieceInfo Boundary

Session: `port-dev-markerpdf-attach7pdf-20260602T121925Z`
Micro-slice: `embedded-file-associated-pieceinfo-boundary-currentbase-20260602T121925Z`
Base accepted HEAD: `2e440dee0eae70f765a6db81b7ef5cdb782abf1d`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates visible PDF text extraction to `pdftext.dictionary_output()` and pypdfium page text in `marker/pdf/extract_text.py`. Native fallback scanning must preserve that page-text boundary and keep FileSpec/PieceInfo private streams as review metadata, not Gutenberg paragraph text:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The PDF parser boundary is the FileSpec and page/catalog metadata surface. pypdf constants list page `/PieceInfo`, page `/AF`, FileSpec `/EF`, and FileSpec filename/description keys as metadata entries separate from page `/Contents`:

- https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html

## Native Behavior Added

`PdfTextExtractor` now resolves indirect application dictionaries inside a `/PieceInfo` dictionary before collecting `/Private` stream object numbers for fallback-stream exclusion.

This closes the catalog-associated FileSpec shape:

```text
/AF [10 0 R]
10 0 obj << /Type /Filespec /PieceInfo << /WPImport 30 0 R >> /EF << /F 11 0 R >> >>
30 0 obj << /LastModified (...) /Private 31 0 R >>
31 0 obj << /Type /Metadata ... >> stream ... endstream
```

`PdfEmbeddedFileExtractor` already resolved that indirect PieceInfo application dictionary for review metadata. The missing boundary was fallback text extraction on attachment-only PDFs, where the private stream could be decoded as visible text. The patch keeps the attachment row and private-stream checksum metadata while excluding the private payload from fallback paragraphs.

## Red-First Evidence

Before the fix, an attachment-only catalog `/AF` PDF with `/PieceInfo << /WPImport 30 0 R >>` leaked:

```text
Indirect App PieceInfo Leak
```

through `PdfTextExtractor::extractPlainText()`. After the fix, the same fixture returns empty visible text while `PdfEmbeddedFileExtractor` reports `piece_info.WPImport.private_stream.object=31`.

## Verification

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
1 test files, 274 assertions, 0 failures
```

Adjacent embedded/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
2 test files, 813 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
65 test files, 3872 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-associated-pieceinfo-indirect-boundary.php
```

The smoke emits `attachment_count=1`, `relationship=Source`, `piece_info_applications=["WPImport"]`, `piece_info_private_object=31`, `piece_info_private_checksum_matches=true`, `excluded_attachment_payload_text=true`, `excluded_pieceinfo_private_stream_text=true`, `visible_text_empty=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and structural checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-associated-pieceinfo-indirect-boundary.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-associated-pieceinfo-indirect-boundary.php

jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
passed with no output

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat catalog `/AF` associated-file extraction, portfolio `/Collection` propagation, embedded-file `/Params /CheckSum`, direct FileSpec `/PieceInfo /Private` stream metadata, catalog-associated PieceInfo checksum metadata, or token-aware direct stream-owner recovery. The new boundary is specifically indirect application dictionaries inside associated FileSpec `/PieceInfo` when fallback all-stream text extraction is otherwise active.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, dictionary/value parsing, FileSpec `/EF` parsing, embedded-file review metadata, PieceInfo private-stream metadata, and fallback stream exclusion. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, pypdfium2, Surya, tabled, Texify, Torch/model downloads, and live app/server workflows.

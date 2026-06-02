# markerPDF Portfolio Filespec PieceInfo Private Streams

Session: `port-dev-markerpdf-portfile18-20260602T082558Z`
Micro-slice: `portfolio-embeddedfile-pieceinfo-filespec-boundaries-20260602T082558Z`
Base accepted HEAD: `ea8c64405378734c2ac9cf76b52c2cfc8459a74b`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets visible PDF text through `pdftext.dictionary_output()` and pypdfium page text APIs in `marker/pdf/extract_text.py`; native fallback extraction must preserve that page-text boundary and not convert non-page metadata streams into WordPress paragraphs:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The PDF parser dependency boundary is the PDF FileSpec and page/catalog metadata surface. pypdf's constants list FileSpec `/F`, `/UF`, platform filename keys, `/EF`, `/RF`, `/Desc`, and page `/PieceInfo` as PDF dictionary entries, all separate from page `/Contents` text:

- https://pypdf.readthedocs.io/en/4.3.0/_modules/pypdf/constants.html

## Native Behavior Added

`PdfEmbeddedFileExtractor` now treats Filespec `/PieceInfo` `/Private` streams as review-only metadata. It reports object number, decoded byte length, SHA-256, declared stream length, MIME subtype, and filters, but does not expose private stream bytes in attachment rows.

`PdfTextExtractor` now excludes stream objects referenced from resolved `/PieceInfo` `/Private` dictionaries during whole-file fallback stream scanning. This prevents producer-private portfolio metadata that happens to contain PDF-looking text operators from leaking into visible Gutenberg paragraphs.

The red-first fixture leaked:

```text
PieceInfo Private Leak
```

After the fix, the attachment row still carries `piece_info.WPImporter.private_stream`, while `PdfTextExtractor::extractPlainText()` returns an empty string for the payload-only fixture.

## Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-portfolio-pieceinfo-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-portfolio-pieceinfo-import.php
```

Focused tests:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
1 test files, 138 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 446 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests
59 test files, 2697 assertions, 0 failures

git diff --check -- lanes/markerpdf
passed with no output
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-portfolio-pieceinfo-import.php
```

The smoke emits `attachment_count=2`, `private_stream_piece_info_objects=[33]`, `excluded_pieceinfo_private_stream_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with only `Portfolio Cover` rendered as paragraph text.

## Non-Overlap

This does not repeat the accepted catalog EmbeddedFiles name-tree, catalog `/AF`, `/Collection`, Filespec `/CI`, Filespec dictionary `/PieceInfo`, embedded-file `/Params /CheckSum`, or FileSpec `/EF` payload exclusion slices. It adds only the remaining boundary where `/PieceInfo /Private` resolves to a producer-private stream object.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, dictionary parsing, stream decoding, FileSpec attachment review metadata, and fallback text exclusion. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

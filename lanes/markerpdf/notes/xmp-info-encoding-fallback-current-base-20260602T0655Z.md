# markerPDF XMP/Info Encoding Fallback Slice

Date: 2026-06-02 06:54 UTC

Micro-slice: `markerpdf-xmp-info-encoding-fallback-current-base-20260602T0655Z`

## Behavior

`PdfMetadataExtractor` now normalizes XMP metadata packets before DOM parsing:

- UTF-8 BOM packets are stripped before parsing.
- UTF-16BE/UTF-16LE BOM packets are converted to UTF-8 before parsing.
- Declared non-UTF-8 XML encodings are available as converted fallback candidates when the direct XML parse fails.
- Undeclared invalid-UTF-8 XMP packets try bounded Windows-1252, then ISO-8859-1 conversion before being rejected.

When the recovered XMP packet omits a field such as authors, the existing trailer `/Info` path still supplies the missing value through PDFDocEncoding string decoding. XMP and `/Info` metadata remain review metadata only and do not leak into visible page text.

## Source Truth

- Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates initial PDF text/metadata document loading through pypdfium/pdftext boundaries in `marker/pdf/extract_text.py` and carries conversion metadata through `marker/convert.py`.
- PDF parser source truth: XML metadata streams are RDF/XML packets; declared XML encodings are parser input, while malformed or nonconforming packets should fail closed to `/Info` rather than becoming visible text.
- Local dependency evidence: PHP/libxml `DOMDocument::loadXML()` accepts declared `Windows-1252` XML and decodes smart quotes, but rejects undeclared invalid UTF-8 bytes. This slice adds only the bounded undeclared-packet fallback before DOM parsing.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: failed `decodes undeclared Windows-1252 XMP packet bytes and falls back to Info authors`; source was `["info"]` instead of expected `["xmp","info"]`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `1 test files, 144 assertions, 0 failures`.

MarkerPDF lane gate:

`php tools/run-tests.php lanes/markerpdf/tests`

Result: `58 test files, 2430 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-info-encoding-fallback-import.php`

Result: emitted decoded XMP title `Café “Review” Packet`, `/Info` author fallback `Łukasz Editor`, `xmp_packet_encoding=Windows-1252`, `xmp_encoding_fallback=true`, `metadata_not_visible_text=true`, and native-only flags.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-info-encoding-fallback-import.php`

Result: no syntax errors.

Diff hygiene:

`git diff --check -- lanes/markerpdf`

Result: passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, `/Info` PDFDocEncoding decoder, DOM XML parser, and PHP `iconv` conversion already used elsewhere in the lane. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

## Non-Overlap

This does not repeat the accepted PDFDocEncoding trailer `/Info` metadata slice, XMP timezone normalization, catalog XMP extraction, XFA/XDP UTF-16 stream packet extraction, or visible text font-encoding slices. The new behavior is specifically XMP packet byte decoding before metadata parse plus `/Info` field fallback.

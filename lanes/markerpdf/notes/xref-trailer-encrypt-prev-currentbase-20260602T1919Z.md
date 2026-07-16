# markerPDF xref trailer Encrypt Prev current-base

Micro-slice: `xref-trailer-encrypt-prev-currentbase`
Session: `port-dev-markerpdf-xref40pdf-20260602T1919Z`
Base accepted HEAD: `4dc1f21b98948ff243f10a6054e126d012098006`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` delegates low-level PDF parsing and text extraction to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction. `marker/convert.py::convert_single_pdf()` consumes those extracted page blocks before OCR/layout/table/model work. That makes xref traversal and encrypted-document fail-closed behavior a native parser/dependency boundary for this PHP lane.

## Behavior

PDF incremental updates can put the current page tree in the latest xref stream while omitting `/Encrypt` from that latest trailer dictionary and relying on a previous `/Prev` trailer. `PdfTextExtractor` already followed that `/Prev` chain for text blocking, but `PdfMetadataExtractor` only inspected the latest trailer dictionary when building encryption/security metadata. `PdfSecurityPreflight` could therefore report such a document as unencrypted even though native text extraction was already blocked.

`PdfMetadataExtractor` now walks the latest `startxref` trailer chain for `/Encrypt` before falling back to loose textual/xref-stream scans. A current trailer `/Encrypt null` remains authoritative and stops stale previous encryption from leaking forward; an omitted current `/Encrypt` follows `/Prev` and records `prev_trailer_encrypt` or `prev_xref_stream_trailer_encrypt` as the source.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php
```

Failed with the inherited `/Prev` case reporting metadata source `['xmp', 'trailer_id']` instead of `['encryption', 'xmp', 'trailer_id']`.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php
```

Passed: 1 test file, 30 assertions, 0 failures.

Affected metadata/security/text gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: 5 test files, 1966 assertions, 0 failures.

Adjacent xref/parser gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Passed: 27 test files, 331 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-trailer-encrypt-prev-currentbase.php
```

Passed with `encrypted=true`, `text_policy=blocked_without_decryption`, `encryption_source=prev_trailer_encrypt`, `xmp_preserved=true`, and `raw_key_material_exposed=false`.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-trailer-encrypt-prev-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat accepted current xref-stream trailer metadata, current xref-stream `/Encrypt` with `EncryptMetadata false`, latest textual trailer `/Encrypt null`/`/ID` precedence, xref-stream `/Prev` index-width generation repair, hybrid object-stream owner repair, xref free-entry suppression, or public-key/Standard permission review. The new boundary is specifically inherited trailer encryption through `/Prev` when the current xref-stream trailer omits `/Encrypt`, plus the explicit current `/Encrypt null` stop condition.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref table/stream trailer parser, `/Prev` chain walker, metadata extractor, security preflight, text extractor, and WordPress smoke path. Full upstream parity remains gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark workflow tooling, and external OCR/rendering helpers.

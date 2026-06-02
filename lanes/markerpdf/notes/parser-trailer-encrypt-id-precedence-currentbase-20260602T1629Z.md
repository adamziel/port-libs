# markerPDF parser trailer Encrypt/ID precedence current base

Micro-slice: `parser-trailer-encrypt-id-precedence-review-currentbase-20260602T1629Z`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates to pdftext and `naive_get_text()` delegates to pypdfium/PDFium-style document parsing before conversion.
- PDF parser behavior: the latest `startxref` section is the current trailer boundary; `/Prev` sections are older revisions. Trailer keys such as `/Root`, `/Info`, `/ID`, and `/Encrypt` are selected from the current xref table/stream first, with previous sections only used when the current trailer does not supply the key. An explicit current `/Encrypt null` must not be overridden by stale prior xref tables or stale xref-stream dictionaries.
- Relevant sources: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py> and PDFium parser trailer/xref handling in `core/fpdfapi/parser/cpdf_parser.cpp`.

## Implemented Behavior

- `PdfTextExtractor::hasEncryptedTrailer()` now consults the latest `startxref` chain before fallback scanning.
- The chain parser checks current xref table trailers, companion hybrid `/XRefStm` dictionaries, xref-stream trailers, and then `/Prev` sections. The first explicit `/Encrypt` value wins; `null` clears stale encryption while an omitted key can still inherit from `/Prev`.
- `PdfMetadataExtractor` now treats an explicit current trailer `/Encrypt` value, including `null`, as authoritative before scanning loose xref-stream dictionaries. This prevents stale xref-stream `/Encrypt` dictionaries from resurrecting encryption after the current trailer cleared it.
- The focused fixture builds an incremental PDF with a stale encrypted prior revision, stale prior `/ID`, and a stale xref-stream dictionary, followed by a current trailer with `/Encrypt null`, current `/Info`, and current `/ID`. WordPress text import uses the current clear page, while metadata and security preflight use the current ID and unencrypted import boundary.

## Red Baseline

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses latest trailer Encrypt null and ID before stale encrypted Prev trailers
Values are not identical
Expected: array (
  0 => 'Current trailer clear page',
  1 => 'Encrypt null wins',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses latest trailer Encrypt null and ID before stale encrypted Prev trailers
1 test files, 26 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
7 test files, 1211 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-trailer-encrypt-id-precedence-currentbase.php
current_text_imported=true
stale_text_excluded=true
current_id_selected=true
stale_id_excluded=true
stale_encryption_suppressed=true
raw_key_material_exposed=false
executes_decryption=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted xref-stream trailer `/Root`/`Info`/`ID` metadata, current xref-stream `/Encrypt` metadata-source boundaries, Standard encryption permission review, xref free-entry suppression, hybrid object-stream generation precedence, stream-owned xref offset rejection, or xref-stream `/Prev` object-stream owner exclusion. This slice is specifically the explicit current-trailer `/Encrypt null` and `/ID` precedence boundary over stale encrypted previous revisions.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` traversal, trailer dictionary parser, metadata merger, security preflight, text extractor, and WordPress smoke path. Full upstream markerPDF parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch models, tabled-pdf, Texify, OCR/rendering helpers, Streamlit/FastAPI runtime paths, benchmark tooling, and model downloads.

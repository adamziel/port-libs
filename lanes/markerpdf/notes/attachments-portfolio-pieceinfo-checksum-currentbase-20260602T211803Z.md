# markerPDF Attachments Portfolio PieceInfo Checksum Current Base

Session: `port-dev-markerpdf-attach57-20260602T211803Z`

Micro-slice: `attachments-portfolio-pieceinfo-checksum-currentbase`

Base accepted HEAD: `21d06ebd1b6613951a7951bffd383999ec33281d`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF parsing/text extraction behind the pdftext/PDFium boundary in `marker/pdf/extract_text.py`; native PHP review metadata must not become visible Markdown/WordPress text.

The PDF-side boundary is the Portfolio attachment model: catalog `/Collection` defines review schema/sort state, catalog `/AF` points at associated FileSpec dictionaries, FileSpec `/CI` carries collection-item values, FileSpec `/PieceInfo` carries producer-private application state, and embedded-file or PieceInfo private-stream `/Params /CheckSum` is an MD5 checksum of decoded bytes. These rows are attachment review metadata, not document XMP roots or visible page content.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL keeps current portfolio associated FileSpec PieceInfo checksum metadata review-only
Expected: 'D:20260602211900Z'
Actual: NULL
1 test files, 18 assertions, 1 failures
```

The current source carried `/Collection` schema fields and embedded-file checksum state on `collection.associated_files`, but those Portfolio-associated FileSpec rows omitted `/PieceInfo`.

## Implementation

`PdfMetadataExtractor` now attaches FileSpec `/PieceInfo` to catalog `/Collection` associated-file rows. It also summarizes checksum-bearing PieceInfo private streams as review-only metadata:

- direct `/Private 41 0 R` streams become `private_stream` plus a `private_streams` row keyed as `Private`;
- dictionary entries such as `/Private << /PrivateStream 33 0 R >>` keep their existing raw private review dictionary and add checksum-bearing `private_streams`;
- provenance gains `filespec_pieceinfo_private_streams` only for streams with checksum review state, so existing PieceInfo XMP stream provenance remains non-duplicated;
- decoded stream bytes are hashed and checksum-reviewed, but the stream payload is not included in document metadata rows.

The new current-base fixture uses an xref stream to select the current catalog, Portfolio collection, FileSpecs, and PieceInfo private streams before stale appended objects. It proves stale attachment and private-stream payloads do not win.

## Verification

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-portfolio-associated-pieceinfo-checksum-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php
```

Passed: `1 test files, 60 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed: `5 test files, 529 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 838 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-portfolio-associated-pieceinfo-checksum-currentbase.php
```

Passed: emitted `collection_associated_file_count=2`, filenames `["source.xml","preview.json"]`, relationships `["Source","Alternative"]`, embedded checksum matches `[true,false]`, PieceInfo private checksum matches `[true,false]`, provenance source `filespec_pieceinfo_private_streams`, `current_xref_selected=true`, `visible_text_excludes_attachment_payloads=true`, and `visible_text_excludes_pieceinfo_private_streams=true`.

```sh
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Passed: no output.

## Status Delta

- Behavior tests move `837 -> 838`.
- WordPress scenarios move `837 -> 838`.
- Focused assertion coverage adds the new `60`-assertion current-base test and keeps adjacent attachment/metadata coverage green at `529` assertions.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

This does not repeat accepted embedded-file `/Params /CheckSum`, catalog `/AF` basic association rows, Portfolio `/Collection` propagation into embedded-file extraction, FileSpec `/CI` field-value review, name-tree Portfolio metadata, attachment-local XMP/OutputIntent provenance, PieceInfo XMP provenance, associated FileSpec PieceInfo private-stream extraction in `PdfEmbeddedFileExtractor`, page/StructTree associated-file review, or fallback attachment payload exclusion. The bounded behavior is `PdfMetadataExtractor` catalog `/Collection` associated-file `/PieceInfo` checksum review.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF object/xref selection, dictionary/value parsing, FileSpec review extraction, Portfolio collection schema parsing, stream decoding, embedded-file Params checksum normalization, and visible-text stream exclusion. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, OCR/PIL/Streamlit/FastAPI runtime paths, benchmark scripts, and live Python/model workers.

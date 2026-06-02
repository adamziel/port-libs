# markerPDF Metadata PieceInfo OutputIntent XMP Boundary

Session: `port-dev-markerpdf-metapdf-20260602T105207Z`

Micro-slice: `metadata-pieceinfo-outputintent-xmp-boundary-currentbase-20260602T105207Z`

Base accepted HEAD: `3ee94b2b9b3e6147faa2f27766c75d7097a754ae`

## Source Truth

The local markerPDF upstream cache path referenced by the lane manifest was not present in this isolated environment (`/home/claude/port-libs/.upstream-cache/markerpdf`), so this patch uses the current lane manifest and native parser tests as the local upstream-backed inventory. Public upstream marker/markerPDF behavior remains a PDF-to-Markdown import pipeline with PDF parsing and model dependency boundaries, while this native PHP slice is bounded to catalog metadata extraction.

The PDF parser boundary for this slice is catalog dictionary scope: document XMP `/Metadata` and PDF/A `/OutputIntents` are catalog root entries. Application-private `/PieceInfo` dictionaries can contain keys with the same names, but those nested keys are review metadata and must not be promoted to document XMP or PDF/A roots.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result before fix: failed. The new PieceInfo boundary test expected `source` to be `["catalog"]`, but nested `/PieceInfo` private `/Metadata` and `/OutputIntents` were promoted as `["xmp","output_intents"]`.

## Implementation

`PdfMetadataExtractor` now reads document `/Metadata`, `/OutputIntents`, and `/PieceInfo` only as direct top-level catalog keys. Catalog `/PieceInfo` remains exposed under `catalog.piece_info` as review-only private metadata, including nested dictionary/object-reference summaries, without decoding private XMP packet contents into document-level metadata.

The WordPress smoke fixture keeps visible text clean while proving:

- private PieceInfo XMP is not promoted to document XMP;
- private PieceInfo OutputIntent is not promoted to PDF/A metadata;
- PieceInfo review metadata remains available under the catalog review payload;
- Python/models/external PDF tools are not executed.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 232 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-pieceinfo-outputintent-xmp-boundary.php
```

Passed: emitted `source=["catalog"]`, `pieceinfo_private_xmp_not_promoted=true`, `pieceinfo_outputintent_not_promoted_to_pdfa=true`, and `visible_text="PieceInfo Boundary Body"`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-pieceinfo-outputintent-xmp-boundary.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Passed: `4 test files, 1039 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests
```

Passed: `61 test files, 3383 assertions, 0 failures`.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP/OutputIntent metadata parser, and text extractor. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, and live app/server workflows.

## Non-Overlap

This does not repeat encrypted metadata priority, direct catalog XMP extraction, PDF/A OutputIntent extraction, page/FileSpec PieceInfo review metadata, associated-file PieceInfo checksum metadata, xref-stream metadata boundaries, or text extraction stream-filter work. The bounded behavior is only the nested catalog `/PieceInfo` private key boundary for `/Metadata` and `/OutputIntents`.

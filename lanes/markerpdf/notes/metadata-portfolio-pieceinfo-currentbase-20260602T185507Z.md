# markerPDF Metadata Portfolio PieceInfo Current Base

Session: `port-dev-markerpdf-meta40pdf-20260602T1855Z`

Micro-slice: `metadata-portfolio-pieceinfo-currentbase`

Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` writes Markdown and `_meta.json` as separate artifacts in `marker/output.py`, so Portfolio and PieceInfo review rows belong in metadata, not visible WordPress paragraphs.
- Upstream `marker/pdf/extract_text.py` delegates page text extraction to `pdftext.dictionary_output()`/PDFium text pages before marker conversion. This native PHP slice keeps that boundary by selecting the current PDF object graph before reading Portfolio attachment metadata.
- PDF parser/dependency source truth: pypdf exposes catalog `/Collection`, page/catalog `/PieceInfo`, page/catalog `/AF`, and FileSpec `/EF` as dictionary boundaries. FileSpec `/CI` and `/PieceInfo` are review metadata for attachment import, not active content.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Before the source change, the new current-base Portfolio fixture failed:

```text
FAIL extracts current xref-selected PDF portfolio PieceInfo metadata from EmbeddedFiles name trees
Expected: 2
Actual: 1
1 test files, 312 assertions, 1 failures
```

The standalone `PdfEmbeddedFileExtractor` scanned duplicate direct objects by object number and fell through to stale objects appended after the current xref-stream EOF instead of honoring the latest `startxref` object selection.

## Implementation

`PdfEmbeddedFileExtractor` now builds its object map from token-aware direct object definitions plus the latest `startxref` chain:

- xref table and xref-stream rows select current direct object offsets;
- xref-stream trailer dictionaries supply catalog `/Root` when no textual trailer exists;
- stale duplicate catalog, collection, name-tree, FileSpec, embedded-file, and PieceInfo objects appended after the current EOF remain excluded;
- existing stream boundary scanning is preserved so `endobj`-looking payload text does not truncate objects.

The new WordPress smoke proves current Portfolio `/Collection`, EmbeddedFiles name-tree FileSpec `/CI`, catalog/FileSpec `/PieceInfo`, and visible page text are selected while stale attachments and PieceInfo private stream text stay out of Gutenberg paragraphs.

## Verification

Red-first focused command before source fix:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Failed: `1 test files, 312 assertions, 1 failures`.

After implementation:

```sh
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-portfolio-pieceinfo-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed: `1 test files, 341 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: `3 test files, 1723 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-portfolio-pieceinfo-currentbase.php
```

Passed: emitted `filename="current-source.xml"`, `portfolio_view="T"`, `portfolio_priority_display="P2"`, `pieceinfo_manifest="current-portfolio-1908"`, `stale_duplicates_excluded=true`, `attachment_payload_text_excluded=true`, and `pieceinfo_private_stream_text_excluded=true`.

## Status Delta

- Behavior tests move `659 -> 660` pass / `0` fail.
- Mapped markerPDF semantics move `482 -> 483 / 78`.
- WordPress scenarios move `659 -> 660`.

## Non-Overlap

This does not repeat accepted `PdfMetadataExtractor` catalog `/AF` PieceInfo provenance, catalog Collection associated-file metadata, catalog name-tree limit filtering, current xref page-text repair, object-stream repair, page `/AF`, page `/PieceInfo`, or FileSpec private-stream checksum slices. The bounded behavior is the standalone `PdfEmbeddedFileExtractor` current xref-selected Portfolio `/Collection` plus EmbeddedFiles name-tree `/CI` and `/PieceInfo` review path.

## Dependency Closure

No new support component is needed. This reuses native PHP object scanning, xref stream decoding, dictionary/value parsing, stream decoding, embedded-file Params checksum review, Portfolio schema field review, PieceInfo private-stream review, and WordPress smoke paths. Full upstream runner parity remains gated by Python/model/runtime dependencies: `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, Streamlit/FastAPI, OCR tooling, and benchmark workflows.

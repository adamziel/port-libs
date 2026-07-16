# markerPDF Attachment FileSpec AFRelationship Checksum Current Base

Session: `port-dev-markerpdf-attach62-20260602T213538Z`

Micro-slice: `attachment-filespec-associated-afrelationship-checksum-currentbase`

Base accepted HEAD: `99591cbc6337f72bc79127211674461d42c783cc`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reads PDF page text through `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py` and writes Markdown and metadata as separate output artifacts in `marker/output.py`:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py

Relevant PDF parser behavior: pypdf constants expose page/catalog `/AF`, catalog `/Names`, FileSpec `/F`, `/UF`, `/EF`, `/RF`, and `/Desc` as dictionary entries rather than visible page text. PDF associated-file review must therefore keep FileSpec relationship and embedded-file checksum state in attachment metadata while excluding attachment payload bytes from Gutenberg paragraphs:

- https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes current xref-selected associated FileSpec AFRelationship and checksum review (lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php)
Values are not identical
Expected: 'associated_file_provenance'
Actual: NULL

1 test files, 10 assertions, 1 failures
```

The standalone `PdfEmbeddedFileExtractor` already emitted raw attachment fields and Portfolio/PieceInfo provenance, but non-Portfolio catalog `/AF` rows did not expose a review-only relationship/checksum provenance summary.

## Implementation

`PdfEmbeddedFileExtractor` now adds generic `associated_file_provenance` for standalone associated FileSpec rows:

- standard PDF 2.0 `/AFRelationship` values map to role labels such as `original_source`;
- custom relationship names are marked `unrecognized_pdf_associated_file_relationship`;
- missing `/AFRelationship` is marked `missing_pdf_associated_file_relationship`;
- embedded-file `/Params /CheckSum` is summarized as MD5 match state over decoded bytes;
- provenance payload rows include filename, MIME type, byte count, declared size match, SHA-256, checksum, computed checksum, and match state;
- the low-level extractor may still return `content`, but provenance is explicitly `review_only` and `payload_included=false`.

Existing Portfolio/PieceInfo provenance remains under `provenance_review`; when that richer provenance exists, the new generic associated-file row is exposed as `associated_file_provenance_review`.

The current-base fixture uses a xref stream to select the current catalog `/AF` array and three current FileSpec rows before stale appended Catalog/FileSpec/EmbeddedFile objects after EOF.

## Verification

```sh
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-filespec-afrelationship-checksum-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
```

Passed: `1 test files, 46 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed: `2 test files, 387 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-attachment-filespec-afrelationship-checksum-currentbase.php
```

Passed: emitted `attachment_count=3`, filenames `["source.xml","review.json","orphan-review.json"]`, relationship statuses `["standard_pdf_associated_file_relationship","unrecognized_pdf_associated_file_relationship","missing_pdf_associated_file_relationship"]`, checksum matches `[true,false,true]`, `current_xref_selected=true`, and `visible_text_excludes_attachment_payloads=true`.

```sh
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Passed: no output.

Root harness: not run; isolated micro-slice.

## Status Delta

- Behavior tests move `855 -> 856`.
- WordPress scenarios move `855 -> 856`.
- Focused assertion coverage adds a new `46`-assertion current-base attachment test and keeps adjacent embedded-file coverage green at `387` assertions.
- Expected mapped semantics move `601 -> 602 / 78`.

## Non-Overlap

This does not repeat accepted embedded-file `/Params /CheckSum` extraction, basic catalog `/AF` attachment rows, Portfolio `/Collection` propagation, FileSpec `/CI` field review, Portfolio/PieceInfo checksum review, FileSpec-local XMP/OutputIntent provenance, related-file `/RF` review, page/StructTree associated-file review, current xref trailer metadata, or stale object-stream/xref recovery by itself. The bounded behavior is standalone `PdfEmbeddedFileExtractor` provenance for current xref-selected associated FileSpec `/AFRelationship` plus embedded-file checksum state, including standard, unrecognized, and missing relationship outcomes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, startxref/xref-stream current-object selection, dictionary/value parsing, FileSpec `/EF` stream decoder, embedded-file Params checksum normalization, and visible-text stream exclusion. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, OCR/PIL/Streamlit/FastAPI runtime paths, benchmark scripts, and live Python/model workers; none were executed for this bounded native PHP slice.

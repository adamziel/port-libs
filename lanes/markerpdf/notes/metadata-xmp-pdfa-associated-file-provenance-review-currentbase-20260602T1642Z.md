# markerPDF XMP PDF/A Associated-File Provenance Review

Session: `port-dev-markerpdf-meta30pdf-20260602T1642Z`

Micro-slice: `metadata-xmp-pdfa-associated-file-provenance-review-currentbase-20260602T1642Z`

Base accepted HEAD: `2c21071f7e9064c624f93392d27c864177463373`

## Source Truth

Upstream `sddai/markerPDF` keeps converted Markdown and metadata as separate output artifacts in `marker/output.py` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, and obtains PDF page text through pdftext/PDFium boundaries in `marker/pdf/extract_text.py`. Native markerPDF must therefore keep associated-file payloads and attachment-local metadata out of visible Gutenberg paragraphs and document-level XMP/PDF-A roots.

Relevant parser behavior comes from PDF dictionary boundaries also reflected by pypdf constants: catalog dictionaries may carry `/OutputIntents` and `/AF`, FileSpec dictionaries carry `/EF`, `/Desc`, `/AFRelationship`, `/Metadata`, and local `/OutputIntents`, and PDF 2.0 AFRelationship values include `Source`, `Data`, `Alternative`, `Supplement`, `EncryptedPayload`, `FormData`, `Schema`, and `Unspecified`.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL summarizes associated FileSpec XMP and PDF/A provenance without promoting payloads
Expected: 'associated_file_provenance'
Actual: NULL
1 test files, 366 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor` now adds `provenance_review` to OutputIntent-associated and catalog-collection associated FileSpec rows. The summary is derived review metadata only:

- AFRelationship status and standard role labels for `Source`, `Schema`, and other PDF 2.0 relationship names;
- embedded-file payload filename, MIME type, decoded byte count, SHA-256, declared size, and Params checksum match state;
- decoded FileSpec `/Metadata` stream object number, type/subtype, filters, byte count, and SHA-256 without exposing XMP packet text;
- FileSpec-local `/OutputIntents` identifiers and ICC profile hashes without promoting those rows into document `output_intents` or `pdfa`;
- explicit `payload_included=false` for WordPress import review.

The WordPress smoke emits Source and Schema associated files and proves XMP title text, ICC profile bytes, XML payloads, and schema payloads are absent from visible paragraph output and encoded metadata payload content.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 404 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: `3 test files, 1293 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-pdfa-associated-provenance-review-currentbase.php
```

Passed: emitted `relationship_roles=["original_source","schema_definition"]`, root PDF/A identifier `Root Provenance sRGB`, attachment-local PDF/A identifier `Associated Provenance sRGB`, `payload_content_omitted=true`, `xmp_payload_omitted=true`, `associated_pdfa_not_promoted_to_root=true`, and visible text `Associated Provenance Body`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xmp-pdfa-associated-provenance-review-currentbase.php
```

Passed: no syntax errors.

Final whitespace/JSON gates are recorded in the worker final response.

## Status Delta

- Behavior tests move `564 -> 565`.
- Mapped markerPDF semantics move `404 -> 405 / 78`.
- WordPress scenarios move `564 -> 565`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, dictionary/value tokenizer, stream decoder, XMP stream hash boundary, OutputIntent parser, embedded-file Params checksum review, and visible-text stream exclusions. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI workflows, benchmark tooling, and live Python/model workers.

## Non-Overlap

This does not repeat document-level XMP extraction, root PDF/A OutputIntent extraction, encrypted metadata source priority, OutputIntent-associated FileSpec extraction, catalog Collection schema associated-file extraction, Portfolio FileSpec-local Metadata/OutputIntent raw review, PieceInfo private metadata boundaries, embedded-file checksum extraction, or visible-text stream filter boundaries. The bounded behavior is only a derived provenance summary for associated FileSpec XMP/PDF-A review rows.

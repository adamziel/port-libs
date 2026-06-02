# markerPDF Metadata Associated File Schema Current Base

Session: `port-dev-markerpdf-meta44-20260602T1953Z`

Micro-slice: `metadata-associated-file-schema-currentbase`

Base accepted HEAD: `ca550807cded80a5a0bf98599fdd8ae923c222c8`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps converted Markdown, metadata, and child artifacts separate through `marker/output.py`, while PDF text extraction stays behind the pdftext/PDFium boundary in `marker/pdf/extract_text.py`. Native markerpdf therefore keeps embedded-file payloads and Portfolio review fields out of visible WordPress paragraphs.

The PDF-side boundary for this slice is the Portfolio attachment model: catalog `/Collection /Schema` defines review fields, `/Collection /Sort` defines review ordering, `/Names /EmbeddedFiles` maps name-tree names to FileSpec dictionaries, FileSpec `/CI` carries collection-item values, and embedded-file stream `/Params` supplies size/date/checksum metadata. These schema values are review metadata; embedded payload bytes are not document text or document metadata roots.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL propagates catalog Collection schema to EmbeddedFiles name-tree metadata rows
Expected: 'Migration Source'
Actual: NULL
1 test files, 14 assertions, 1 failures
```

The current source propagated schema fields to catalog `/AF` collection-associated files, but `PdfMetadataExtractor` name-tree `/EmbeddedFiles` rows did not receive FileSpec `/CI` or schema-derived `collection_field_values`.

## Implementation

`PdfMetadataExtractor` now passes catalog `/Collection` metadata into the EmbeddedFiles name-tree FileSpec review path. When a name-tree FileSpec carries `/CI`, metadata rows now include:

- raw `collection_item` values from direct or indirect FileSpec `/CI`;
- schema-derived `collection_field_values` for `/Subtype /F`, `/Desc`, `/Size`, `/ModDate`, `/CreationDate`, `/S`, `/N`, and `/D`;
- `/CollectionSubitem` value, prefix, display value, and type metadata;
- existing checksum, MIME type, relationship, and payload-omission boundaries.

The new WordPress smoke proves Source and Alternative name-tree attachments emit priority display values `P2` and `P1`, preserve verified/stale checksum state, omit payload content from metadata rows, and render only the page paragraph text.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php
```

Passed: `1 test files, 39 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: `4 test files, 1815 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-associated-file-schema-currentbase.php
```

Passed: emitted `schema_fields=["NameField","DescriptionField","ModifiedField","BytesField","Subject","Priority","ReviewDate"]`, `embedded_relationships=["Source","Alternative"]`, `priority_display_values=["P2","P1"]`, `checksum_matches=[true,false]`, `payload_content_omitted=true`, `payload_text_not_visible=true`, and `visible_text="Associated File Schema Body"`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-metadata-associated-file-schema-currentbase.php
```

Passed: no syntax errors.

```sh
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
```

Passed: both JSON files are valid.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Status Delta

- Behavior tests move `745 -> 746`.
- Mapped markerPDF semantics move `532 -> 533 / 78`.
- WordPress scenarios move `745 -> 746`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, catalog metadata extractor, EmbeddedFiles name-tree walker, FileSpec review parser, collection schema parser, embedded-file Params checksum review, and visible-text stream exclusion boundaries. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, OCR/PIL/Streamlit/FastAPI runtime paths, benchmark scripts, and live Python/model workers.

## Non-Overlap

This does not repeat accepted document-level XMP/Info extraction, root PDF/A OutputIntent extraction, catalog `/AF` collection-associated FileSpec schema metadata, ordinary `PdfEmbeddedFileExtractor` Portfolio field-value rows, attachment-local XMP/OutputIntent provenance, FileSpec `/RF` related-file review, PieceInfo private stream checksum review, page `/AF`, StructTree `/AF`, or current xref name-tree limits. The bounded behavior is only schema propagation from catalog `/Collection` into `PdfMetadataExtractor` `/Names /EmbeddedFiles` FileSpec metadata rows.

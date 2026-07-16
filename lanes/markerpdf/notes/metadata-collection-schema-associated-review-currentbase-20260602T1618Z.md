# markerPDF Metadata Collection Schema Associated Review

Session: `port-dev-markerpdf-meta28pdf-20260602T1618Z`

Micro-slice: `metadata-collection-schema-associated-review-currentbase-20260602T1618Z`

Base accepted HEAD: `9192be14c831cb84a6d124eb0733f7e677891025`

## Source Truth

Upstream markerPDF keeps conversion output structured, with document metadata separated from Markdown text and child artifacts. The pinned upstream source file `marker/output.py` writes metadata as a separate output artifact, while the native lane PDF boundary maps PDF Portfolio `/Collection` dictionaries and catalog `/AF` FileSpec rows before WordPress import.

For this slice, the PDF-side source truth is the Portfolio collection boundary: `/Collection /Schema` describes fields, `/Collection /Sort` describes review ordering, and catalog-associated FileSpecs carry attachment-local `/CI`, `/Metadata`, `/OutputIntents`, and embedded-file `/Params`. Those attachment-local streams must remain review metadata and must not become document XMP, document PDF/A roots, or visible page text.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL reviews catalog Collection schema with associated FileSpec rows as metadata
Expected: 'catalog_collection'
Actual: NULL
1 test files, 306 assertions, 1 failures
```

The accepted implementation did not expose `catalog.collection` from `PdfMetadataExtractor`.

## Implementation

`PdfMetadataExtractor` now emits `catalog.collection` and top-level `collection` metadata with:

- catalog `/Collection` source, type, view, default document, schema fields, and sort keys;
- catalog `/AF` FileSpec review rows scoped to the Collection metadata;
- schema-typed field values derived from FileSpec filename/description, embedded-file Params size/date fields, and FileSpec `/CI` collection item values;
- `/CollectionSubitem` value/prefix/display-value handling;
- attachment checksum review states and MIME metadata;
- attachment-local `/Metadata` and `/OutputIntents` review dictionaries without decoding or promoting their XMP/ICC payloads.

The WordPress smoke proves associated payload bytes, XMP title text, and ICC bytes stay out of visible paragraphs and document metadata roots.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 355 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: `3 test files, 1244 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-collection-schema-associated-review-currentbase.php
```

Passed: emitted `source=["catalog"]`, `schema_fields=["NameField","DescriptionField","BytesField","Subject","Priority","ReviewDate"]`, `associated_file_count=2`, `priority_display_values=["P2","P1"]`, `checksum_matches=[true,false]`, `associated_outputintent_not_pdfa_root=true`, `associated_payload_content_omitted=true`, `associated_xmp_payload_omitted=true`, `associated_icc_payload_omitted=true`, and `visible_text="Collection Schema Metadata Body"`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-metadata-collection-schema-associated-review-currentbase.php
```

Passed: no syntax errors.

```sh
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
```

Passed: both lane JSON files valid.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Status Delta

- Behavior tests move `544 -> 545`.
- Mapped markerPDF semantics move `391 -> 392 / 78`.
- WordPress scenarios move `544 -> 545`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary/value tokenizer, stream review decoder, embedded-file Params checksum review, OutputIntent review metadata, and visible-text stream-boundary exclusions. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark workflow tooling.

## Non-Overlap

This does not repeat accepted document-level XMP/Info extraction, root PDF/A OutputIntent extraction, OutputIntent-associated FileSpec review, catalog PieceInfo private Metadata/OutputIntents boundaries, ordinary `PdfEmbeddedFileExtractor` Portfolio field-value rows, attachment-local XMP/OutputIntent review, page `/AF` review metadata, or associated-file PieceInfo checksum slices. The bounded behavior is `PdfMetadataExtractor` catalog `/Collection` schema/sort plus catalog `/AF` associated FileSpec review under document metadata.

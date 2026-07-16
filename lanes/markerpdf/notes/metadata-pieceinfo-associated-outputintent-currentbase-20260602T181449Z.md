# markerPDF metadata PieceInfo associated OutputIntent current-base slice

Micro-slice: `metadata-pieceinfo-associated-outputintent-currentbase-20260602T181449Z`

Base accepted HEAD: `babe129c590f2b2bc17296e92e8321e009789290`

## Source-truth boundary

- Upstream Marker keeps rendered output separated into Markdown, `metadata`, and images, and its metadata dictionary is review data rather than visible page content.
- The upstream pdftext dependency exposes structured text via `dictionary_output()` as page/line/span extraction through pypdfium2. That keeps page text extraction separate from PDF catalog/FileSpec review dictionaries.
- PDF catalog `/AF` FileSpec rows, FileSpec-local `/Metadata`, FileSpec-local `/OutputIntents`, and FileSpec `/PieceInfo` application dictionaries are attachment/review metadata. They must not promote nested XMP titles, ICC profile bytes, or embedded source payloads into document-level metadata roots or WordPress paragraphs.

## Red-first evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Before the implementation, the new test failed with the expected missing `piece_info` row:

```text
FAIL reviews current xref-selected catalog associated FileSpec PieceInfo metadata and OutputIntent provenance
Expected: 'D:20260602181449Z'
Actual: NULL
1 test files, 648 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor::catalogAssociatedFileFromValue()` now mirrors the existing OutputIntent/collection associated-file review path:

- catalog `/AF` FileSpec rows include FileSpec `/PieceInfo` review metadata;
- FileSpec-local `/Metadata` and `/OutputIntents` provenance is attached under `provenance_review`;
- current xref-selected direct object bodies remain authoritative, so stale duplicate FileSpec, XMP, ICC, OutputIntent, and page stream objects appended after the current EOF stay excluded;
- embedded payload bytes and private XMP/ICC streams remain review-only and are not emitted as visible WordPress text.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result after fix:

```text
1 test files, 675 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result:

```text
2 test files, 986 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-metadata-pieceinfo-associated-outputintent-currentbase.php
```

Result: passed; smoke emitted `associated_filename="piece-source.xml"`, `pieceinfo_manifest="piece-181449"`, `pieceinfo_outputintent_identifier="Current PieceInfo Associated sRGB"`, provenance sources `filespec_afrelationship`, `embedded_file_payload_hash`, `embedded_file_params_checksum`, `filespec_metadata_stream`, and `filespec_output_intents`, with `payload_content_omitted=true` and visible text `Current PieceInfo Associated Body`.

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-pieceinfo-associated-outputintent-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: PHP lint passed for all changed PHP files; diff check passed.

## Non-overlap

This does not repeat accepted catalog PieceInfo private Metadata/OutputIntent root-boundary coverage, current xref catalog XMP/Info/root OutputIntent selection, catalog language OutputIntent associated-file review, EmbeddedFiles Portfolio XMP/OutputIntent review, page-associated files, StructTree associated files, or FileSpec PieceInfo private-stream fallback-text exclusion. The bounded behavior is specifically catalog `/AF` FileSpec `/PieceInfo` plus FileSpec-local Metadata/OutputIntent provenance on the current xref-selected metadata path.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object/xref selection, catalog dictionary parsing, FileSpec review, stream decoding, XMP/OutputIntent provenance, and WordPress smoke paths. Full upstream runner parity remains blocked on live Python/pdftext/pypdfium2/Surya/tabled/Texify/model and app/server dependencies.

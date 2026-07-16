# markerPDF Metadata Associated PieceInfo OutputIntent Current Base

Session: `port-dev-markerpdf-meta76-20260602T224546Z`

Micro-slice: `metadata-associated-pieceinfo-outputintent-currentbase`

Base accepted HEAD: `46dcbc383630b2d55e601d02ab9f1a9bd647b8e2`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible text through `pdftext.extraction.dictionary_output(...)` and returns page blocks plus TOC metadata separately, so attachment and private metadata must stay out of visible text: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Upstream `marker/convert.py::convert_single_pdf` keeps conversion metadata in `out_meta` while Markdown text comes from postprocessed page blocks, matching this native split between document roots, review metadata, and Gutenberg paragraph text: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py>.
- Relevant parser/dependency behavior: pypdf constants model `/PieceInfo`, `/AF`, and `/OutputIntents` as page/catalog/FileSpec dictionary keys, not text content, and FileSpec payloads live under `/EF`: <https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html>.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source change:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes catalog AF PieceInfo private OutputIntents in PDF/A associated-file metadata
Expected: array (..., 'filespec_pieceinfo_output_intents')
Actual: array (..., 'filespec_pieceinfo_private_streams')

1 test files, 14 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor` now summarizes catalog `/AF` FileSpec `/PieceInfo /Private /OutputIntents` as associated-file provenance:

- the new `piece_info_pdfa_output_intents` row records application name, last-modified value, PDF/A identifiers, ICC profile hashes, intent count, and individual intent dictionaries;
- `pdfa_associated_files.entries[]` now forwards `piece_info_private_streams` and `piece_info_pdfa_output_intents`;
- the merge-time `pdfa_associated_files.attachment_output_condition_identifiers` summary now includes PieceInfo-local PDF/A OutputIntent identifiers, even when the FileSpec has no top-level `/OutputIntents`;
- attachment XML payloads, PieceInfo private stream bytes, ICC profile bytes, stale appended objects, and private XMP/title strings remain omitted from metadata JSON and visible WordPress text.

This preserves the existing top-level FileSpec `/OutputIntents` provenance path; it adds the missing nested PieceInfo path instead of replacing either surface.

## Verification

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes catalog AF PieceInfo private OutputIntents in PDF/A associated-file metadata

1 test files, 37 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-associated-pieceinfo-outputintent-currentbase.php
```

Passed; emitted `markerpdf-metadata-associated-pieceinfo-outputintent-currentbase` with root PDF/A identifier `Associated PieceInfo Smoke Root PDF/A`, associated filename `associated-piece-smoke.xml`, PieceInfo-local PDF/A identifier `Associated PieceInfo Smoke Attachment PDF/A`, `piece_info_private_stream_checksum_matches=true`, `payload_content_omitted=true`, and visible text `Current Associated PieceInfo OutputIntent Smoke Body`.

Adjacent metadata regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php
```

Passed: `11 test files, 1286 assertions, 0 failures`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-associated-pieceinfo-outputintent-currentbase.php
```

Passed: no syntax errors.

Final local checks:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
git diff --check -- lanes/markerpdf
```

Passed locally after the note/status update.

## Status Delta

- Behavior tests move `930 -> 931`.
- WordPress scenarios move `930 -> 931`.
- Manifest denominator is unchanged: the broader associated PieceInfo/OutputIntent inventory row already exists, and this slice refines the nested PieceInfo-to-PDF/A-summary path without adding a new upstream repository file.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, current xref selector, FileSpec metadata review path, PieceInfo private-stream review, OutputIntent profile hashing, embedded-file Params checksum review, and visible-text exclusion boundaries. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/Torch models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflows, Poetry, and external OCR/PDF helper tooling; none were executed for this bounded native PHP slice.

## Non-Overlap

This does not repeat accepted catalog PieceInfo private Metadata/OutputIntent root-boundary coverage, catalog `/AF` FileSpec metadata extraction, top-level FileSpec `/OutputIntents` provenance, OutputIntent-scoped `/AF` provenance, PDF/A associated name-tree summaries, Portfolio PieceInfo/OutputIntent provenance, PieceInfo private-stream checksum review, encrypted associated-file redaction, page/StructTree associated-file review, or name-tree PieceInfo review. The bounded new behavior is specifically the merge-time PDF/A associated-file summary for OutputIntents nested inside catalog-associated FileSpec `/PieceInfo /Private` dictionaries when no top-level FileSpec `/OutputIntents` is present.

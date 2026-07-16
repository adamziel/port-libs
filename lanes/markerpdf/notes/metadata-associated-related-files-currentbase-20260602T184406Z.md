# markerPDF Metadata Associated Related Files

Micro-slice: `metadata-associated-files-currentbase`

Base accepted HEAD: `4bfec4c2ed04ec45b69266408311f6827e291bfb`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py`, then formats page blocks for downstream conversion. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>. The native PHP fallback must therefore keep attachment and associated-file payload streams outside visible page text.
- The PDF parser/dependency boundary uses pypdf FileSpec constants: `/EF` is the embedded-file dictionary and `/RF` is the related-file dictionary containing embedded-file stream arrays. Catalog/page `/AF` arrays connect associated FileSpec dictionaries to PDF objects. Source: <https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html>.
- PDF associated-file semantics keep FileSpec payloads and related-file sidecars as metadata/provenance. They are not document XMP roots, PDF/A OutputIntent roots, or Gutenberg paragraph text.

## Behavior

Added native review support for FileSpec `/RF` related-file dictionaries:

- `PdfMetadataExtractor` now emits `related_file_count` and `related_files` rows for FileSpec review paths used by catalog `/AF`, Portfolio `/Collection` associated files, OutputIntent `/AF`, and catalog `/Names /EmbeddedFiles`.
- Each related-file row records `/RF` key, related index, embedded stream object, MIME type, filters, size, declared size, checksum match state, and SHA-256, but never includes payload content.
- Associated-file provenance now includes a `filespec_related_files` source with count, `/RF` keys, stream object numbers, MIME types, and hashes.
- `PdfTextExtractor` fallback stream scanning now excludes object references found through `/RF` dictionaries as well as `/EF`, preventing related sidecar streams that contain PDF-looking text operators from becoming visible WordPress text.

## Red/Green Evidence

Red before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
FAIL summarizes associated FileSpec related-file streams as review metadata
Expected: 3
Actual: NULL
1 test files, 778 assertions, 1 failures
```

Green after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
1 test files, 814 assertions, 0 failures
```

Focused lane regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 1722 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-associated-related-files-currentbase.php
```

Passed and emitted `markerpdf-metadata-associated-related-files-currentbase` plus per-related-file review comments. The smoke confirms primary associated-file checksum metadata, two `/RF` related-file rows, omitted related payload content, and visible text `Associated Related File Body`.

Syntax/whitespace:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-associated-related-files-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat accepted catalog `/AF` extraction, Portfolio `/Collection` propagation, OutputIntent-scoped `/AF`, FileSpec-local XMP/PDF-A provenance, page `/AF`, StructTree `/AF`, FileSpec `/PieceInfo` private streams, embedded-file `/Params /CheckSum`, or generic embedded-file name-tree extraction. The bounded behavior is only FileSpec `/RF` related-file review and fallback text exclusion for related-file stream payloads.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, dictionary/value parser, stream decoder, FileSpec metadata review paths, checksum metadata, and text fallback exclusion. Full upstream Python/model/benchmark parity remains gated by `pdftext`, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark workflow tooling.

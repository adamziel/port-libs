# markerPDF Metadata Associated OutputIntent Boundary

Session: `port-dev-markerpdf-meta21pdf-20260602T1455Z`

Micro-slice: `metadata-associated-outputintent-boundary-currentbase-20260602T1455Z`

Base accepted HEAD: `fefb0a4ad04f6c3073002339dc61897861b32ac2`

## Source Truth

The local markerPDF upstream cache path was not present in this isolated worker, so this slice uses the pinned lane manifest and the native PDF parser boundaries already recorded for markerPDF metadata, OutputIntent, FileSpec, and associated-file slices.

Upstream `sddai/markerPDF` delegates PDF document parsing to pdftext/pypdfium-style PDF object/page metadata before conversion. The native PHP boundary here is the PDF dictionary scope: catalog `/OutputIntents` are document-level color/profile metadata, while `/AF` FileSpec entries nested inside an OutputIntent are associated-file review metadata. Associated FileSpec `/Metadata` streams and nested `/OutputIntents` dictionaries must not become document XMP or PDF/A roots, and embedded payload bytes must not become visible WordPress paragraphs.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result before the source fix:

```text
FAIL keeps OutputIntent associated FileSpec metadata review-only
count(): Argument #1 ($value) must be of type Countable|array, null given
1 test files, 264 assertions, 1 failures
```

The new fixture expected `output_intents[0].associated_files`, but the accepted implementation only reported the root OutputIntent profile.

## Implementation

`PdfMetadataExtractor` now reads OutputIntent `/AF` arrays and emits review-only `associated_files` rows with:

- source and alternative FileSpec relationship metadata;
- FileSpec description and filename metadata;
- embedded-file object, MIME type, decoded size, SHA-256, Params `/Size`, Params `/CheckSum`, computed MD5, and checksum match state;
- nested FileSpec `/Metadata` stream dictionaries as `metadata_review` without decoding XMP packet contents;
- nested FileSpec `/OutputIntents` dictionaries as `output_intents_review` without adding them to document-level `output_intents` or `pdfa`.

The WordPress smoke proves the root PDF/A identifier remains `Root sRGB`, while associated `Associated sRGB` OutputIntent dictionaries stay nested review metadata.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 302 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed: `3 test files, 1140 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-associated-outputintent-boundary.php
```

Passed: emitted `source=["output_intents"]`, `pdfa_identifiers=["Root sRGB"]`, `associated_file_count=2`, `associated_relationships=["Source","Alternative"]`, `associated_outputintent_not_pdfa_root=true`, `associated_payload_content_omitted=true`, `associated_xmp_not_promoted=true`, and `visible_text="OutputIntent Associated Body"`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-associated-outputintent-boundary.php
```

Passed: no syntax errors.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Status Delta

- Behavior tests move `522 -> 523`.
- Mapped markerPDF semantics move `370 -> 371 / 78`.
- WordPress scenarios move `522 -> 523`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary/value tokenizer, stream decoder, OutputIntent metadata parser, FileSpec review metadata patterns, and existing text extraction stream-boundary exclusions. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark workflow tooling.

## Non-Overlap

This does not repeat accepted root PDF/A OutputIntent extraction, encrypted metadata priority, catalog PieceInfo private Metadata/OutputIntents boundaries, catalog `/AF` associated-file extraction, Filespec PieceInfo checksum review, page `/AF` review metadata, xref/object-stream repair, or generic stream-filter text boundaries. The bounded behavior is only OutputIntent-scoped `/AF` FileSpec metadata and its nested Metadata/OutputIntent non-promotion boundary.

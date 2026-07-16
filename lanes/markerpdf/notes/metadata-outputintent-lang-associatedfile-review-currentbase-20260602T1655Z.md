# markerPDF Metadata OutputIntent Language Associated File Review

Session: `port-dev-markerpdf-meta31pdf-20260602T1655Z`

Micro-slice: `metadata-outputintent-lang-associatedfile-review-currentbase-20260602T1655Z`

Base accepted HEAD: `16897955fedbe8eb586eccc43fee984b6415532f`

## Source Truth

Upstream `sddai/markerPDF` keeps PDF text extraction and output metadata separated: `marker/pdf/extract_text.py::get_text_blocks` returns page blocks plus TOC metadata, `marker/convert.py::convert_single_pdf` builds `out_meta` separately from final Markdown text, and `marker/output.py::save_markdown` writes the metadata JSON beside the Markdown output.

The PDF-side source truth for this slice is the catalog metadata boundary: catalog `/Lang` is document language review metadata, catalog `/OutputIntents` are document-level color/PDF-A metadata, and catalog `/AF` FileSpec entries can exist without a Portfolio `/Collection`. FileSpec-local `/Metadata`, nested `/OutputIntents`, and embedded-file streams stay attachment review metadata; they must not become document XMP roots, document PDF/A roots, or visible WordPress paragraphs.

## Implementation

`PdfMetadataExtractor` now emits `catalog.associated_files` and top-level `associated_files` for catalog `/AF` arrays when no `/Collection` dictionary is present.

Each review row preserves:

- FileSpec filename, Unicode filename, platform filename, description, `/AFRelationship`, and optional FileSpec `/Lang`.
- Embedded-file object, MIME type, decoded size, SHA-256, Params `/Size`, Params `/CheckSum`, computed MD5, checksum match state, and Params dates.
- FileSpec-local `/Metadata` dictionaries as `metadata_review`.
- FileSpec-local `/OutputIntents` dictionaries as `output_intents_review`.

The root catalog OutputIntent still contributes `pdfa`; associated-file nested OutputIntents do not.

## Verification

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-metadata-outputintent-lang-associatedfile-review-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 476 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: `3 test files, 1365 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-outputintent-lang-associatedfile-review-currentbase.php
```

Passed: smoke emitted `language=en-US`, `pdfa_identifiers=["Root catalog sRGB"]`, `associated_file_count=2`, `associated_languages=["es-MX","fr-CA"]`, `associated_relationships=["Source","Alternative"]`, `associated_outputintent_not_pdfa_root=true`, `associated_payload_content_omitted=true`, `associated_xmp_not_promoted=true`, and `visible_text="Catalog Associated Metadata Body"`.

```sh
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . ": valid JSON\n"; }'
```

Passed before metadata edits; rerun after metadata edits also passed.

```sh
git diff --check -- lanes/markerpdf
```

Passed before metadata edits; rerun after metadata edits also passed.

## Status Delta

- Behavior tests move `577 -> 578`.
- Mapped markerPDF semantics move `414 -> 415 / 78`.
- WordPress scenarios move `577 -> 578`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary/value tokenizer, stream decoder, embedded-file Params checksum review, OutputIntent metadata review, PDF text stream-boundary exclusions, and WordPress smoke pattern. Full upstream Python/model/benchmark parity remains gated by pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark workflow tooling.

## Non-Overlap

This does not repeat accepted root PDF/A OutputIntent extraction, OutputIntent-associated FileSpec review, Portfolio `/Collection` schema associated-file metadata, ordinary `PdfEmbeddedFileExtractor` catalog `/AF` payload extraction, catalog PieceInfo private Metadata/OutputIntents boundaries, page `/AF` review metadata, associated Filespec PieceInfo checksum review, catalog `/Lang` viewer preference extraction, or StructTreeRoot language review. The bounded behavior is `PdfMetadataExtractor` catalog `/AF` review metadata without a Portfolio `/Collection`, combined with existing root OutputIntent and catalog language metadata.

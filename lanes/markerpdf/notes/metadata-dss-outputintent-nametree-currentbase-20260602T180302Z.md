# markerPDF Metadata DSS OutputIntent NameTree Review

Session: `port-dev-markerpdf-meta36pdf-20260602T180302Z`

Micro-slice: `metadata-dss-outputintent-nametree-currentbase-20260602T180302Z`

Base accepted HEAD: `25465d4bad4c4ed7e39379fb65c3e5365a4df98d`

## Source Truth

Upstream `sddai/markerPDF` keeps extracted page text and output metadata separate: PDF text extraction feeds page blocks, while conversion/output layers write metadata beside Markdown rather than promoting metadata streams into visible content. The current isolated worktree does not include a local markerPDF upstream checkout, so this slice uses the lane manifest plus accepted native PDF parser tests as the local source inventory.

The PDF-side boundary for this slice is catalog metadata review:

- Catalog `/Metadata` is the document XMP root.
- Catalog `/OutputIntents` are document-level color/PDF-A metadata.
- Catalog `/DSS` is long-term-validation review material for signatures; this PHP lane hashes and counts Cert/OCSP/CRL/timestamp streams but does not validate signatures, revocation, timestamps, or trust chains.
- Catalog `/Names /EmbeddedFiles` is a FileSpec name tree; embedded payload bytes, FileSpec-local XMP, and nested FileSpec OutputIntents stay attachment review metadata.
- Catalog `/Names /Dests` is navigation metadata; destination names and page-view operands stay out of title/author fallback and visible WordPress paragraphs.

## Implemented

`PdfMetadataExtractor` now emits combined document metadata review rows for PDFs that carry XMP, root OutputIntent metadata, catalog DSS validation material, and name-tree entries:

- `catalog.document_security_store` and top-level `document_security_store` reuse the existing `PdfDocumentSecurityStoreExtractor` DSS summaries.
- `catalog.embedded_files` and top-level `embedded_files` expose review-only FileSpec rows from `/Names /EmbeddedFiles`.
- Name-tree FileSpec rows preserve filename, Unicode filename, `/AFRelationship`, `/Lang`, MIME type, decoded size, content SHA-256, Params checksum match, FileSpec-local `/Metadata` dictionary review, FileSpec-local `/OutputIntents` review, and provenance hashes, but omit raw embedded content.
- Existing root `/OutputIntents` still contribute `pdfa`; nested FileSpec OutputIntents do not.
- Existing destination name-tree extraction composes in the same metadata payload, proving navigation names do not become visible text.

Added WordPress smoke `examples/wordpress-pdf-metadata-dss-outputintent-nametree-currentbase.php`.

## Verification

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
```

Passed: no syntax errors.

```sh
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: no syntax errors.

```sh
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-dss-outputintent-nametree-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 684 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed: `3 test files, 1470 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-dss-outputintent-nametree-currentbase.php
```

Passed: smoke emitted `source=["xmp","catalog","output_intents"]`, `pdfa_identifiers=["Document sRGB"]`, `embedded_name_tree_files=["source.xml"]`, `embedded_payload_content_omitted=true`, `dss_validation_stream_count=3`, `dss_raw_validation_bytes_exposed=false`, `destination_names=["Review Start"]`, and `visible_text="Metadata DSS OutputIntent NameTree Body"`.

```sh
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . ": valid JSON\n"; }'
```

Passed: both lane JSON files are valid.

```sh
git diff --check -- lanes/markerpdf
```

Passed.

## Status Delta

- Behavior tests move `621 -> 622`.
- Mapped markerPDF semantics move `453 -> 454 / 78`.
- WordPress scenarios move `621 -> 622`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, top-level dictionary/value tokenizer, stream decoder, DSS validation-stream summarizer, OutputIntent profile hashing, embedded-file Params checksum review, name-tree destination parser, text stream-boundary exclusions, and WordPress smoke pattern.

Full CMS/PKCS#7 validation, X.509 parsing, OCSP/CRL validation, RFC 3161 timestamp validation, trust-store handling, PDF decryption, rasterization, Python model execution, and external PDF tools remain out of scope. Activating those paths would require separate native cryptographic/decryption and rendering components with signed/tampered/encrypted fixtures before acceptance.

## Non-Overlap

This does not repeat accepted direct catalog XMP extraction, root PDF/A OutputIntent extraction, OutputIntent-associated FileSpec review, Portfolio `/Collection` associated-file review, catalog `/AF` review, ordinary `PdfEmbeddedFileExtractor` payload extraction, catalog PieceInfo private Metadata/OutputIntents boundaries, standalone DSS stream hashing, indirect DSS filter operands, public-key DSS permission review, signature ByteRange/DSS/DocMDP correlation, encrypted metadata source priority, or standalone destination name-tree extraction.

The bounded behavior is the combined document metadata review path where catalog `/DSS`, root `/OutputIntents`, `/Names /EmbeddedFiles`, and `/Names /Dests` coexist without promoting validation bytes, attachment payloads, nested XMP, nested ICC profiles, or navigation names into visible WordPress content.

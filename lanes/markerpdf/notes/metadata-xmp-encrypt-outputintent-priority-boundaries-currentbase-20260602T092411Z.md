# markerPDF Metadata Security Priority Boundaries

Session: `port-dev-markerpdf-meta25-20260602T092411Z`
Micro-slice: `metadata-xmp-encrypt-outputintent-priority-boundaries-currentbase-20260602T092411Z`
Base accepted HEAD: `1592307c62019df9b6a61804a3555dd05bc5ee24`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches PDF text and document metadata through pdftext/pypdfium-style parsing before model conversion. This native slice keeps the same fail-closed parser boundary for encrypted PDFs: without a password/decryption step, encrypted PDF strings and streams cannot be trusted as XMP, Info, OutputIntent, or visible WordPress text.

Relevant PDF parser behavior: a Standard security handler encrypts PDF strings and streams; `/EncryptMetadata false` is the bounded exception that leaves the catalog metadata stream readable. The native extractor therefore preserves XMP only in that explicit case while suppressing trailer `/Info` strings and catalog `/OutputIntents` strings/profile streams on encrypted documents.

## Implemented Behavior

- `PdfMetadataExtractor` now reads `/Encrypt` first and emits `encryption.metadata_source_policy`.
- On encrypted PDFs with `/EncryptMetadata true` or the default, catalog `/Metadata` XMP, trailer `/Info`, and catalog `/OutputIntents` are suppressed before merge.
- On encrypted PDFs with `/EncryptMetadata false`, XMP is preserved, but trailer `/Info` and `/OutputIntents` remain suppressed because no native decryption ran.
- Metadata `source` order now places `encryption` before preserved clear XMP on encrypted documents.
- Raw owner/user keys and cleartext-looking encrypted metadata are not exposed in returned metadata or WordPress smoke output.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
FAIL prioritizes encryption before XMP Info and OutputIntent metadata boundaries
Expected: array (0 => 'encryption')
Actual: array (0 => 'xmp', 1 => 'info', 2 => 'output_intents', 3 => 'encryption')
1 test files, 167 assertions, 1 failures
```

Passing focused gates after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
1 test files, 192 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
1 test files, 44 assertions, 0 failures
```

Lane-only markerPDF gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
61 test files, 2984 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-security-priority.php
```

The smoke emitted `encrypted_source=["encryption"]`, `encrypt_metadata_true_suppressed=["xmp","info","output_intents"]`, `encrypt_metadata_false_preserved=["xmp"]`, `encrypted_text_blocked=true`, `xmp_preserved_when_unencrypted=true`, `info_outputintent_suppressed_when_encrypted=true`, and `raw_key_material_exposed=false`.

Syntax checks passed for:

- `lanes/markerpdf/src/PdfMetadataExtractor.php`
- `lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-metadata-security-priority.php`

`git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `451 -> 452`.
- Mapped markerPDF semantics move `303 -> 304 / 78`.

## Non-Overlap

This does not repeat accepted XMP extraction, OutputIntent review metadata, Standard encryption permission metadata, xref-stream trailer precedence, encrypted text fail-closed extraction, or security preflight signature ByteRange review. It covers the combined priority boundary when those metadata sources coexist on an encrypted document.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object/trailer parser, security-handler metadata parser, XMP decoder, OutputIntent parser, and encrypted-text fail-closed preflight. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtimes, and benchmark workflow tooling.

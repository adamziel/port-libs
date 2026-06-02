# markerPDF Encryption Permission Metadata Slice

Micro-slice: `markerpdf-encryption-permission-metadata-current-base-20260602T0539Z`

Source truth:

- Upstream markerPDF reaches PDF document text/metadata through pdftext/pypdfium-style document preflight before model conversion.
- The lane already maps encrypted-PDF fail-closed extraction preflight. This slice adds the adjacent PDF Standard security-handler metadata boundary from the trailer `/Encrypt` dictionary without attempting password validation or content decryption.

Implemented behavior:

- `PdfMetadataExtractor` resolves trailer and xref-stream `/Encrypt` dictionaries.
- Standard security-handler review metadata now includes `/Filter`, `/SubFilter`, `/V`, `/R`, `/Length`, `/EncryptMetadata`, `/StmF`, `/StrF`, `/EFF`, `/CF` crypt-filter dictionaries, signed and unsigned `/P` permission values, allow/deny permission labels, print quality, and hashed `/Perms` validation bytes.
- Encrypted content extraction remains fail-closed through `PdfTextExtractor`; the WordPress example confirms encrypted cleartext is not imported.
- Raw `/O`, `/U`, `/OE`, and `/UE` key bytes are not exposed in metadata or the example output.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` failed before implementation at missing `encryption` metadata, with `1 test files, 75 assertions, 1 failures`.
- After implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `1 test files, 106 assertions, 0 failures`.
- Example: `php lanes/markerpdf/examples/wordpress-pdf-encryption-permission-metadata-import.php` emitted encrypted-PDF review metadata, `encrypted_text_blocked=true`, permission hex `FFFF0A14`, expected allowed/denied permission labels, and `raw_owner_user_keys_exposed=false`.

Status delta:

- Behavior tests move `393 -> 394`.
- Mapped semantics move `248 -> 249 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF object/trailer parser, dictionary value parser, and encrypted-PDF fail-closed preflight.
- Full upstream Python/model/benchmark parity remains dependency-gated on Poetry plus pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch, model downloads, and live benchmark tooling.

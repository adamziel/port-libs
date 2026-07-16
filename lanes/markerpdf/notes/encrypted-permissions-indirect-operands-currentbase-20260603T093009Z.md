# Encrypted permissions indirect operands current base, 2026-06-03

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260603T093009Z`

Base accepted HEAD: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py` and pdftext/PDFium boundaries before model conversion. The native PHP lane must keep encrypted content fail-closed unless a native decryption component is explicitly activated.
- PDF encryption dictionaries and crypt-filter dictionaries are ordinary PDF dictionaries, so scalar operands such as `/Filter`, `/SubFilter`, `/V`, `/R`, `/Length`, `/P`, `/EncryptMetadata`, `/StmF`, `/StrF`, `/EFF`, crypt-filter rows under `/CF`, and crypt-filter `/CFM`, `/AuthEvent`, `/Length`, and `/Recipients` can be direct or indirect objects.
- Relevant sources: `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`, `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`, and `https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf`.

## Implemented Behavior

- `PdfMetadataExtractor` now resolves indirect encryption dictionary scalar operands before building metadata and security preflight review:
  `/Filter`, `/SubFilter`, `/V`, `/R`, `/Length`, `/P`, `/EncryptMetadata`, `/StmF`, `/StrF`, and `/EFF`.
- `/CF` crypt-filter maps now accept indirect filter dictionaries, and crypt-filter `/CFM`, `/AuthEvent`, `/Length`, and `/Recipients` values resolve indirect operands before Standard authentication review or public-key recipient selection.
- Standard encrypted PDFs with indirect `/P -44`, indirect `/EncryptMetadata false`, and indirect `/StdCF` now preserve clear root XMP review metadata, emit permission hex `FFFFFFD4`, classify copy/extract as allowed only after decryption, and keep native text extraction blocked.
- Public-key encrypted PDFs with indirect `/Filter /Adobe.PubSec`, `/SubFilter /adbe.pkcs7.s5`, indirect `/StmF`/`/StrF`/`/EFF`, and indirect crypt-filter recipient dictionaries now select the document and embedded-file recipient filters while leaving unselected recipient filters review-only.
- Raw owner/user key material, file encryption keys, public-key recipient envelope bytes, encrypted visible text, decryption, permission enforcement, Python/models, and external PDF tools remain excluded.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct Standard `/P` permission preflight, malformed reserved-bit review, unsupported handler review, Standard authentication digest review, public-key recipient envelope inventory, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review. The new boundary is specifically indirect encryption permission and crypt-filter operand resolution before current-base encrypted import decisions.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php` failed on current base with missing preserved XMP title for indirect `/EncryptMetadata false`, raw indirect `/Filter` object-number reporting (`Actual: '6'`), and `1 test files, 6 assertions, 2 failures`.
- Focused new test: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php` passed with `1 test files, 77 assertions, 0 failures`.
- Focused security/metadata regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `6 test files, 1582 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-indirect-permission-preflight-currentbase.php` emitted `standard_text_blocked=true`, `standard_title_preserved=Indirect Permission Root Title`, `standard_permission_hex=FFFFFFD4`, `standard_policy=copy_extract_allowed_after_decryption`, `standard_stream_filter=StdCF`, `standard_embedded_file_filter=EmbeddedIdentity`, `public_key_text_blocked=true`, `public_key_policy=public_key_recipient_permissions_blocked_without_private_key`, `public_key_selected_recipient_count=2`, and all decryption/permission-enforcement/model/external-tool execution flags false.
- PHP lint passed for `PdfMetadataExtractor.php`, `PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php`, and `wordpress-pdf-encrypted-indirect-permission-preflight-currentbase.php`.
- `lane-status.json` decoded successfully after the status update.

## Status Delta

- Focused PHP behavior tests move `1017 -> 1019` PASS cases.
- WordPress scenarios move `1017 -> 1019`.
- No mapped upstream denominator change is claimed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, dictionary value resolver, encrypted text fail-closed gate, Standard permission review, Standard authentication digest review, public-key recipient inventory, and security preflight report path.

Full Standard security-handler decryption, password validation, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, revocation checking, trust-chain validation, OCR/model execution, and external PDF tooling remain out of scope for this no-GPU native parser slice.

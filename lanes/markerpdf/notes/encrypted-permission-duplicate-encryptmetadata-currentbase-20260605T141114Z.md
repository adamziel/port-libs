# markerPDF duplicate EncryptMetadata permission preflight

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T141114Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T141114Z`
Base accepted HEAD: `54014a2e5cfc4e891b85ec9c8033c5cf262fa147`

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates encrypted searchable-PDF text and metadata boundaries to the parser/pdftext/PDFium layer before model/OCR stages. This native PHP lane keeps encrypted content fail-closed unless the parser can prove a specific review source is not encrypted.
- PDF encryption dictionaries define `/EncryptMetadata` as an optional boolean that defaults to `true`; only a single valid `false` declaration permits root XMP metadata-stream review without decryption. Duplicate or malformed declarations are ambiguous and must not preserve XMP.

## Implementation

- `PdfMetadataExtractor` now builds an `encrypt_metadata_declaration_review` for top-level `/EncryptMetadata` entries.
- A single valid boolean remains trusted: omitted values default to `true`, and a single valid `false` still preserves root XMP under the existing encrypted metadata policy.
- Duplicate, malformed, composite, or unresolved `/EncryptMetadata` operands default fail-closed to effective `true`, suppress encrypted XMP, and report `encrypt_metadata_defaulted_fail_closed=true`.
- `PdfSecurityPreflight` now surfaces the declaration review, metadata status, and `encrypt_metadata_fail_closed` review reason without decrypting, enforcing permissions, executing actions, or exposing Standard owner/user validation bytes.

## Evidence

Red-first before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataBoundaryCurrentBaseTest.php
```

Failed with `1 test files, 2 assertions, 1 failures`; source was `["encryption","xmp"]` where the expected source was `["encryption"]`.

Focused after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataBoundaryCurrentBaseTest.php
```

Passed with `1 test files, 37 assertions, 0 failures`.

Adjacent security/metadata regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed with `6 test files, 1815 assertions, 0 failures`.

Broader encrypted/security family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*Test.php lanes/markerpdf/tests/PdfSecurity*Test.php lanes/markerpdf/tests/PdfAttachmentEncrypted*Test.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Passed with `47 test files, 4072 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-encryptmetadata-currentbase.php
```

Reported encrypted text blocked, metadata source `["encryption"]`, XMP policy `suppressed_encrypted_metadata_stream`, status `duplicate_encrypt_metadata_entries_review`, `encrypt_metadata_defaulted_fail_closed=true`, `raw_material_exposed=false`, and no Python/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted encrypted fail-closed text extraction, `/P` signed/unsigned normalization, malformed permission words, revision-gated permission bits, Standard authentication digest review, default crypt-filter roles, public-key recipient envelopes, `/EncryptMetadata false` preservation for a single valid declaration, xref `/Prev` Encrypt inheritance, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is specifically duplicate or malformed `/EncryptMetadata` declaration preflight before WordPress metadata import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary entry parser, XMP metadata policy, security preflight report, and WordPress smoke path. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.

# markerpdf encrypted permission reserved bits current-base

## Source truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates encrypted/searchable PDF parsing to parser-backed PDF layers before OCR/layout/model stages.
- Under the current no-GPU markerPDF scope, this lane owns native PHP security preflight for encrypted PDF import decisions. Standard security-handler `/P` permission words are review metadata only until password validation and decryption exist.
- PDF Standard security-handler permission words require reserved permission bits to have the expected set/clear state. A parseable integer with malformed reserved bits must not be treated as a trusted copy/extract grant.

## Behavior

`PdfSecurityPreflight` now requires a well-formed Standard `/P` word before exposing trusted allowed/denied permission summaries, copy/accessibility grants, print quality, or permission bit rows in review-facing preflight output.

The raw `PdfMetadataExtractor` `standard_permissions` record still preserves the parsed integer, hex word, raw bit-derived names, and reserved-bit violations for review. Encrypted text extraction remains blocked, no decryption or permission enforcement is executed, and raw owner/user authentication bytes stay out of JSON and WordPress output.

## Red-first evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionReservedBitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when Standard permission reserved bits are malformed before import preflight
Values are not identical
Expected: NULL
Actual: true
PASS keeps malformed reserved-bit encrypted payloads out of visible WordPress text

1 test files, 35 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionReservedBitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard permission reserved bits are malformed before import preflight
PASS keeps malformed reserved-bit encrypted payloads out of visible WordPress text

1 test files, 74 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
Focused test run: 28 selected test files (root lock skipped)
...
28 test files, 2375 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-reserved-bits-currentbase.php
```

The smoke emits `policy=permissions_malformed_blocked_without_decryption`, `trusted_permission_bit_count=0`, `copy_or_extract_allowed=null`, `permission_bits_reliable=false`, `raw_material_exposed=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted missing `/P`, non-integer/composite `/P`, out-of-range `/P`, unsigned/plus-normalized `/P`, duplicate `/P`, crypt-filter, AuthEvent, EncryptMetadata, public-key recipient, or Standard authentication-material preflight slices. The bounded behavior is only parseable Standard permission words whose reserved bits are malformed.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, encryption dictionary parser, Standard permission bit parser, security preflight, encrypted text guard, and WordPress smoke renderer. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.

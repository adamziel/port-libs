# Pandoc ODF/ODT Package Font Metadata - 20260612T033917Z

Bead: plib-l4vm2

Scope: compact OpenDocument/ODT package ingestion for embedded font package
parts.

`OpenDocumentPackage` now classifies manifest-declared and undeclared font
package parts as metadata-only review items. Font package parts are detected by
`Fonts/` package paths and by font media types such as `font/woff2`,
`font/woff`, `font/ttf`, `application/vnd.ms-opentype`, and related legacy
font MIME aliases.

The package summary now reports font package evidence through:

- `packageFonts` metadata-only rows with media type provenance, byte lengths,
  CRCs, declared/undeclared state, missing/encrypted/invalid-media diagnostics,
  and `package-font-metadata-only` review policy.
- `packageInventory` `font-package` roles and `fontPackagePartCount`.
- `manifestReview` `fontPackagePartCount` and `fontPackageItems`.

Declared font bytes are blocked from normal manifest/media exposure with
`font-package-bytes-blocked`, while stored lengths and CRC provenance remain
available for review. Font package parts stay out of `mediaParts`.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 715 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70121 assertions, 0 failures

No external Pandoc, office, TeX, browser, zip/unzip, Jupyter, Node, external
validator, or live-service tooling was used.

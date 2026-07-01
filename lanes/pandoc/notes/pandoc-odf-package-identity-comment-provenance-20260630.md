# ODF/ODT package identity ZIP comment provenance 2026-06-30

## Scope

- `OpenDocumentPackage` now carries ZIP entry comment provenance into metadata-only package identity entries, including decoded comment text, byte length, encoding, has-comment flags, and non-empty issue lists.
- The canonical package identity hash now incorporates package-level ZIP comment preflight metadata, so a package-comment-only change changes `identitySha256` even when package parts and exposed media bytes are unchanged.
- Public identity summary counters now include package/comment flags and commented entry names. Package inventory remains the detailed public surface for package-level comment text.

## Byte Exposure

- No ODT package part bytes are newly exposed.
- Media handoff and byte-exposure policies remain unchanged; identity stays `odf-package-identity-metadata-only` with `canExposeBytes=false`.
- Direct-format parity accounting remains active in `lane-status.json`; this slice only closes native ODF/ODT package-ingestion provenance.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed with 1,912 assertions and 0 failures.

The broader rich ODF reader file remains covered by the existing known-baseline note and was not used as the package-ingestion gate for this slice.

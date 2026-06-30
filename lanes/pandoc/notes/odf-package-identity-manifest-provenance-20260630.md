# ODF package identity manifest provenance slice

Date: 2026-06-30
Hook: plib-ec2i5

## Scope

This slice keeps the compact ODT package reader inside native PHP package-ingestion
work. It extends `OpenDocumentPackage` metadata-only package identity records with
manifest provenance the parser already captures:

- manifest path query, fragment, and URI-encoded package-reference flags;
- media-type parameters and parameter maps;
- preferred view mode;
- custom manifest attributes and namespace declarations;
- root manifest custom attributes and namespace declarations;
- package-inventory manifest counterparts for the same fields.

The identity path remains metadata-only and keeps package bytes blocked.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`

Focused result: 1 file, 1925 assertions, 0 failures.

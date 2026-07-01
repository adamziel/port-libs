# OPC content type parameter provenance 2026-06-30

## Scope

- `OpcContentTypes` now exposes structured metadata for valid MIME content type parameters in preflight records.
- Parameter provenance includes media type, parameter count, normalized parameter names, decoded parameter map, raw parameter values, quoted flags, quoted-pair flags, and semicolon-in-value flags.
- `contentTypeResolutionForPart()` carries the same metadata so shared OPC ZIP manifest and selected-part preflights can report parameterized defaults and overrides without re-parsing content type strings.

## Byte Exposure

- No ZIP package payload bytes are newly exposed.
- The slice only parses `[Content_Types].xml` declarations already read by shared OPC package preflight.
- Direct-format parity accounting remains active in `lane-status.json`; this is a shared ZIP/OPC package primitive for DOCX/EPUB/ODF package ingestion.

## Validation

- `php -l lanes/pandoc/src/OpcContentTypes.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with 4,694 assertions and 0 failures.

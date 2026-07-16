# pandoc-opc-xml-relationships-core-current-base-20260609T120618Z

Accepted base: `67d434edf3a4d801f81c24c8c2a09230a63f024a`

## Behavior

- Added relationship Type URI percent-encoding guards to
  `OpcRelationship::relationshipTypePreflight()`.
- Invalid relationship Type values now report
  `relationship-type-malformed-percent-escape` for broken `%` escapes and
  `relationship-type-unsafe-percent-encoded-byte` for percent-encoded control
  bytes.
- Valid percent-encoded spaces in absolute relationship Type URIs remain
  accepted.
- The new diagnostics propagate through `OpcRelationshipGraph` target
  preflight and reachable closure rows so import review can separate an
  existing target part from an unsafe relationship role URI.
- Added a WordPress DOCX OPC smoke example that surfaces invalid relationship
  Type rows for reviewer audit.

## Focused Evidence

- `php -l lanes/pandoc/src/OpcRelationship.php` -> no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> no syntax
  errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-relationship-type-percent-preflight.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  -> `1 test files, 3677 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-relationship-type-percent-preflight.php --self-test`
  -> `wordpress-docx-opc-relationship-type-percent-preflight self-test passed`.

Baseline from the latest accepted OPC note was
`OpenPackagingConventionsTest.php` at `1 test files, 3643 assertions,
0 failures`, so this slice adds 34 focused assertions inside an existing PHP
PASS case. `phpPass` is unchanged.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcRelationships`, `OpcRelationshipGraph`, `OpcRelationship`,
`OpcContentTypes`, and existing lane TestRunner support.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, XMLDSig validator, external XML tool, external converter, online
service, live provider test, live-service provider test, root harness, model,
or GPU path was run.

## Non-Overlap

This does not repeat accepted OPC slices for external target percent escapes,
internal target path/query/fragment checks, relationship Id validation,
content-type declaration collisions, signature transform/digest policy,
relationship role target policies, or source-closure inventory. It closes the
relationship Type URI percent-encoding preflight gap only.

## Follow-Up

Useful non-overlapping OPC follow-ups are relationship mode/content-type role
cross-checks, embedded package closure handoff, and package-wide role policy
checks.

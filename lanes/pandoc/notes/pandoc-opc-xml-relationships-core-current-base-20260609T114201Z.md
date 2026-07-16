# pandoc-opc-xml-relationships-core-current-base-20260609T114201Z

Accepted base: `6015fe7e84dc103ae25bd946b46459a21033d320`

## Behavior

- Added `OpcContentTypes::preflightXml()` to inventory `[Content_Types].xml`
  `Default` and `Override` declarations without weakening strict
  `OpcContentTypes::fromXml()` parsing.
- Added `OpcRelationshipGraph::preflightContentTypesInPackage()` so OPC/DOCX
  import preflight can report missing `[Content_Types].xml`, duplicate default
  extension declarations, and duplicate override part-name equivalence groups
  before package graph construction.
- Added a WordPress DOCX OPC smoke example that exposes duplicate groups and
  keeps strict parser rejection visible for reviewer queues.

## Focused Evidence

- `php -l lanes/pandoc/src/OpcContentTypes.php` -> no syntax errors.
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php` -> no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-content-type-collision-preflight.php` -> no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  -> `1 test files, 3643 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-content-type-collision-preflight.php --self-test`
  -> `wordpress-docx-opc-content-type-collision-preflight self-test passed`.

- `git diff --check -- lanes/pandoc` -> clean.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcContentTypes`, `OpcRelationshipGraph`, `OpcPackagePath`,
`OpcMarkupCompatibility`, `XmlHtmlDom`, and the existing lane TestRunner.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, XMLDSig validator, external XML tool, external converter, online
service, live provider test, live-service provider test, root harness, model,
or GPU path was run.

## Non-Overlap

This does not repeat accepted OPC slices for relationship target preflight,
relationship Id validation, relationship source closure inventory, Markup
Compatibility preserve/ignorable handling, package-signature transform content
type lookup, external target policy, or relationship record-shape diagnostics.
It closes a content-type declaration collision preflight gap while preserving
strict parser rejection.

## Follow-Up

Useful non-overlapping OPC follow-ups are relationship mode/content-type role
cross-checks, signature transform canonicalization metadata, or embedded
package relationship closure handoff.

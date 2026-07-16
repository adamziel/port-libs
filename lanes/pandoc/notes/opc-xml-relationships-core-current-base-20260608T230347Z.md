# OPC XML relationships selector policy slice

Date: 2026-06-08 UTC
Base accepted HEAD: d8ca989a03aa98e6028adc24e3edc39bb34ec9a6
Micro-slice: pandoc-opc-xml-relationships-core-current-base-20260608T230347Z

## Scope

- Tightened XML signature `RelationshipTransform` selector parsing to accept only the OPC singular `mdssi:RelationshipGroupReference` element for SourceType group selection.
- Rejected the plural `mdssi:RelationshipsGroupReference` spelling as `unsupported-relationship-transform-child` instead of silently selecting matching relationships.
- Added selector summary counts to signature relationship-transform preflight rows: total meaningful selector children, relationship-reference children, relationship-group-reference children, unsupported selector children, and unsupported selector content.
- Surfaced those counts in the WordPress DOCX OPC preflight smoke for the valid signature transform and selector-shape guard.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2713 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2755 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors detected
- Whitespace: `git diff --check -- lanes/pandoc`
  - Result: passed with no output

## Dependency Closure

No new support component is needed. This slice reuses native PHP OPC relationship XML parsing, package-signature relationship transform preflight, selector materialization, and the existing WordPress DOCX OPC preflight smoke. It did not run Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.

## Non-overlap

This does not repeat the accepted OPC content-type inventory, package URI validation, embedded-package closure, Markup Compatibility AlternateContent, custom XML properties, encrypted-package relationship policy, or XMLDSig digest-policy slices. It focuses only on relationship-transform selector XML grammar and selector-count review metadata.

## Follow-up

A next non-overlapping OPC slice could cross-check XMLDSig package-object `Reference` URIs against relationship-transform selector rows, or add deeper canonicalization profile metadata without invoking external signature validators.

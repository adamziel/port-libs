# OPC Relationships Nested Payload Segment

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T204951Z`

Base accepted HEAD: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Behavior

Implemented a bounded OPC relationship-part shape guard for relationship part names that include payload path segments after the `_rels` directory. A package part such as `/word/_rels/media/document.xml.rels` now fails preflight as `invalid-relationship-part-name` instead of being normalized to relationships for `/word/media/document.xml`.

This preserves the already accepted relationship-source metadata case for legal nested relationship-part sources such as `/word/_rels/_rels/document.xml.rels.rels`, where the payload relationship part is the single `.rels` file inside the final `_rels` directory.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2545 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`
- `php -l lanes/pandoc/src/OpcRelationships.php`
  - `No syntax errors detected in lanes/pandoc/src/OpcRelationships.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - passed with no output

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed for this slice. It reuses native `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and `OpcPackagePath` relationship-part preflight.

Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This slice avoids the accepted OPC content-type inventory, signature reference content-type query, relationship Id validation, TargetMode diagnostics, external-target policy, package-root target policy, and Pack URI trailing-dot/raw-whitespace target validation clusters. It only closes the relationship-part source-name shape gap for nested payload path segments under `_rels`.

## Follow-Up

Next OPC work should stay non-overlapping, for example encrypted package relationship policy, signature object/digest metadata, or higher-level DOCX integration of existing preflight results.

# Pandoc OPC Relationships Current-Base Slice

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T025954Z`
Base accepted HEAD: `55ec937c4c82a12943c4829891dcf143a18f7fa2`

## Behavior

OPC XML digital signatures use the package RelationshipTransform to select
relationships by `RelationshipReference SourceId` or
`RelationshipGroupReference SourceType` before canonicalization. A transform
with no usable selector is invalid even when the surrounding `ds:Reference URI`
is itself same-document, external, missing, or otherwise not resolvable to an
OPC `.rels` part.

`OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now gets
`empty-relationship-selector` directly from the transform selector parser. That
keeps selector-shape diagnostics visible before relationship-part source
resolution or transform materialization is possible.

## Changes

- Added selector-parser empty-selector diagnostics in
  `lanes/pandoc/src/OpcRelationshipGraph.php`.
- Added focused coverage for same-document, external, and resolvable
  RelationshipTransform references with no selector children in
  `lanes/pandoc/tests/OpenPackagingConventionsTest.php`.
- Added
  `lanes/pandoc/examples/wordpress-docx-opc-signature-empty-selector-preflight.php`
  as a lane-local WordPress DOCX signature review smoke.
- Updated `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` for this slice.

## Verification

- No port-pandoc rework notes were present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/`.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2025 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed as expected with `1 test files, 2034 assertions, 1 failures`; the
  same-document unresolved Reference URI missed `empty-relationship-selector`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2052 assertions, 0 failures`.
- Example:
  `php lanes/pandoc/examples/wordpress-docx-opc-signature-empty-selector-preflight.php --self-test`
  passed.
- Syntax:
  `php -l lanes/pandoc/src/OpcRelationshipGraph.php`,
  `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-opc-signature-empty-selector-preflight.php`
  passed.
- Whitespace:
  `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native
`OpcRelationshipGraph`, `OpcRelationships`, `XmlHtmlDom`, and `ZipPackage`
support plus focused PHP tests/examples. Pandoc, Word, LibreOffice, zip/unzip,
XMLDSig validators, Cabal/Haskell runners, external XML tools, online services,
live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This avoids already mapped OPC relationship work for content-type inventory,
Pack URI part-name/trailing-dot validation, signature Reference `ContentType`
query parsing, case-equivalent relationship references, singular group
selector aliasing, reachable relationship closure, and package-part reference
inventory. The new coverage is only the XMLDSig RelationshipTransform
empty-selector diagnostic before relationship reference resolution.

## Next

A useful follow-up would be another bounded, non-overlapping OPC graph gap:
additional package-signature transform policy, fixed content-types references,
or DOCX relationship role handoff not already covered by the current OPC tests.

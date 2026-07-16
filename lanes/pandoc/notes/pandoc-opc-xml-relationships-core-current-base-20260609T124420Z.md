# pandoc-opc-xml-relationships-core-current-base-20260609T124420Z

Accepted base: `a38edfb50352ef212fcb62803d82a7ae9bd2908c`

## Behavior

- Added `OpcRelationshipGraph` preflight diagnostics for `TargetMode="External"`
  relationships whose non-empty relative or package-absolute target resolves to
  an existing OPC package part.
- The new `external-target-matches-package-part` issue is additive: ordinary
  absolute web links and remote relative external references remain unchanged,
  while likely local-package target-mode mistakes are marked invalid for import
  review.
- Added a WordPress DOCX OPC smoke example that surfaces local media,
  `customXml`, and style part shadows before importer queues treat them as
  normal external URLs.

## Focused Evidence

Red-first focused run after adding the test:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  -> `1 test files, 3707 assertions, 1 failures`
  (`rIdExternalLocalImage` was still considered valid).

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  -> `1 test files, 3718 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-target-mode-shadow-preflight.php --self-test`
  -> `wordpress-docx-opc-target-mode-shadow-preflight self-test passed`.

Baseline from the latest accepted OPC note was
`OpenPackagingConventionsTest.php` at `1 test files, 3677 assertions,
0 failures`, so this slice adds 1 focused PHP PASS case and 41 focused
assertions. `phpPass` moves from 2778 to 2779 and mapped OPC relationship target
preflight coverage moves from 6 to 7.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcRelationshipGraph`, `OpcRelationships`, `OpcPackagePath`, `OpcContentTypes`,
and the existing lane TestRunner.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, XMLDSig validator, external XML tool, external converter, online
service, live provider test, live-service provider test, root harness, model,
or GPU path was run.

## Non-Overlap

This does not repeat accepted OPC slices for relationship Type percent-escape
guards, external target percent guards, package-root relative external base
policy, relationship transform selectors, signature digest policy, content-type
collision preflight, relationship source closure inventory, or role target
policy summaries. It closes only the target-mode/package-local shadow
diagnostic gap.

## Follow-Up

Useful non-overlapping OPC follow-ups are a compact target-mode policy summary
for importer gates, package relationship role allowlists, or embedded package
closure handoff.

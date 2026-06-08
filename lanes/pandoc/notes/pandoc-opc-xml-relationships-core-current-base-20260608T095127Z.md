# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260608T095127Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T095127Z`
Base accepted HEAD: `f37923538221acd51c7fa0f16b86121e0ff32955`

## Behavior

Tightened direct OPC relationship package loaders so package-local `.rels`
parts are loadable only when `[Content_Types].xml` identifies them as
`application/vnd.openxmlformats-package.relationships+xml`.

- `OpcRelationships::packageHasRelationshipsForSource()` now returns `false`
  for a discovered relationship part whose package content type is not the OPC
  relationships content type.
- `OpcRelationships::fromPackage()` now rejects the same invalid content-typed
  relationship part instead of parsing it solely because the path name ends in
  `.rels`.
- Packages without `[Content_Types].xml` keep the existing direct fixture
  behavior.
- The WordPress DOCX OPC preflight smoke now exposes this lower-level
  direct-loader guard beside the existing graph preflight
  `invalid-relationship-content-type` diagnostic.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused run before adding the new assertion: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2105 assertions, 0 failures`.
- Red-first run after adding the focused regression and before implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed as expected with `1 test files, 2106 assertions, 1 failures`; `packageHasRelationshipsForSource()` still returned `true` for `/word/_rels/comments.xml.rels` even though `[Content_Types].xml` overrode that part as `application/xml`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2111 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint passed for `lanes/pandoc/src/OpcRelationships.php`, `lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

Root harness not run - isolated micro-slice.

Focused delta: `+1` PHP PASS case and `+6` focused assertions in
`OpenPackagingConventionsTest.php`.

## Status Delta

- Lane `phpPass`: `1603 -> 1604`.
- Manifest `benchmarkDenominator.mapped`: `2022 -> 2023`.
- Manifest `mappedOpcRelationshipDirectLoaderContentTypeCases`: `1`.
- Manifest `opcRelationshipDirectLoaderContentTypeAssertions`: `6`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
the focused OPC test harness, and the WordPress DOCX OPC preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`, XMLDSig
validator, external XML tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted OPC content-type inventory, relationship
content-type-on-non-relationship-part graph preflight, reserved `_rels`
directory package membership, Pack URI part-name validation, relationship id
validation, target integrity preflight, reachable closure traversal, digital
signature relationship roles, or package-signature relationship-transform
ContentType query preflight.

It owns only the lower-level direct package relationship loader behavior for
content-typed packages.

## Follow-Up

Continue OPC XML relationships with a non-overlapping package-semantics gap
such as relationship TargetMode policy in a higher-level DOCX handoff,
signature-object manifest coverage, or DOCX reader use of existing
relationship preflight summaries.

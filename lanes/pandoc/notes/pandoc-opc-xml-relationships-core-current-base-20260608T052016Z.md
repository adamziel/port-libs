# Pandoc OPC XML Relationships Current-Base Slice

Session: port-dev-pandoc-opc-relationships-20260608T052016Z
Base accepted HEAD: c162e5af21915b05e444923d010d6e56dffee14f
Micro-slice: pandoc-opc-xml-relationships-core-current-base-20260608T052016Z

## Behavior

Implemented native OPC package preflight diagnostics for non-relationship
package parts placed under reserved `_rels` directories.

- Package members such as `/word/_rels/review-metadata.xml` remain readable as
  package entries, but `preflightPackageParts()` now marks them invalid with
  `reserved-relationship-directory-part` unless they are actual relationship
  parts.
- Content-type overrides targeting such non-relationship parts now report
  `reserved-relationship-directory-override`.
- Internal relationship targets resolving to such parts now report
  `targets-reserved-relationship-directory-part`.
- Relationship parts such as `/word/_rels/document.xml.rels` keep their
  existing valid relationship-part behavior.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused run before adding the new assertion: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2025 assertions, 0 failures`.
- Red-first run after adding the fixture and before implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `1 test files, 2028 assertions, 1 failures`; `/word/_rels/review-metadata.xml` had no `reserved-relationship-directory-part` issue.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2067 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

## Non-Overlap

This slice does not repeat the accepted OPC content-type inventory,
relationship content-type-on-non-relationship-part, Pack URI part-name
validation, content-types item guard, fixed relationship part source, digital
signature, or relationship-transform preflight clusters. It focuses only on
reserved `_rels` directory package membership for ordinary non-relationship
parts.

## Dependency Closure

No new support component is needed. The patch reuses the existing native OPC
content-types, package-path, relationship graph, package reference inventory,
focused OpenPackagingConventions tests, and WordPress DOCX OPC preflight smoke.
Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external XML
tools, online services, live provider tests, and live-service provider tests
were not executed.

## Next

Continue with a non-overlapping OPC package-semantics gap such as relationship
transform selection, source closure policy, digital-signature package role
validation, or DOCX relationship handoff behavior.

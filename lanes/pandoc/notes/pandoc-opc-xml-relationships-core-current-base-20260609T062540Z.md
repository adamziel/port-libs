# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260609T062540Z`
Base accepted HEAD: `fc8eeee0d58103faabecc24a17572b78d812884d`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T062540Z`

## Behavior

- Added `OpcRelationshipGraph::signatureRelationshipTransformSummary()` to aggregate existing XMLDSig `RelationshipTransform` preflight rows into importer-ready provenance:
  - transform validity and issue counts;
  - relationship part and source part provenance;
  - selected relationship IDs;
  - selected internal target parts and external targets;
  - materialized relationship XML payload hashes;
  - compact per-transform audit rows.
- Wired the summary into `wordpress-docx-opc-preflight.php` at the top level and under `wordpressImport`, with self-test checks for signed internal media/package targets, external reviewer links, and transform payload hash provenance.

## Evidence

- Baseline focused run before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3482 assertions, 0 failures`
- Red-first focused run after adding the new test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: failed on missing `OpcRelationshipGraph::signatureRelationshipTransformSummary()`
- Final focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3504 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`

Focused delta: `+1` PHP PASS case, `+22` focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP OPC package stack: `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, relationship transform materialization, signature transform preflight, and the existing WordPress DOCX OPC preflight example. Full XMLDSig cryptographic verification, Pandoc upstream runner parity, Office tools, `zip`/`unzip`, TeX/PDF engines, browser renderers, and online services remain out of scope.

## Non-Overlap

This slice does not repeat the accepted package consistency summary, relationship role target policy summary, package part relationship coverage summary, relationship closure coverage summary, content type inventory, or raw transform materialization/fingerprint rows. It adds a distinct aggregate over already parsed signature relationship transform rows for import-review provenance.

## Next

A useful follow-up is to project OPC consistency and signature summaries into DOCX/EPUB/ODF reader import reports, or add stricter bounded signature reference policy checks without attempting cryptographic signature validation.

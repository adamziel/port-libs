## pandoc-opc-xml-relationships-core-current-base-20260609T054134Z

Accepted base: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`

Behavior added:
- Added `OpcRelationshipGraph::packagePartRelationshipCoverageSummary()` as an importer-facing aggregation over the accepted package part reference inventory.
- The summary groups package parts into direct-and-reachable, direct-only, missing referenced, unreferenced package part, unreferenced relationship part, invalid part, and external target buckets.
- The WordPress DOCX OPC preflight example now exposes the coverage summary in both full output and the compact `wordpressImport` projection.

Focused evidence:
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::packagePartRelationshipCoverageSummary()` at `1 test files, 3374 assertions, 1 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 3410 assertions, 0 failures`.
- Delta: `+1` focused PHP PASS case and `+36` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

Dependency closure:
- No new native PHP support component is needed.
- This slice reuses `OpcRelationshipGraph::packagePartReferenceInventory()`, reachable closure traversal, target preflight, `OpcContentTypes`, `OpcRelationships`, and `OpcPackagePath`.
- Full upstream Pandoc runner parity remains out of scope for this implementation lane and belongs to upstream-runner dependency work.

Exclusions:
- Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, tar, gzip, lz4, TeX/PDF engines, Typst, browser renderers, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.
- Root harness not run - isolated micro-slice.

Next non-overlapping OPC follow-up:
- Wire the package-part coverage summary into a DOCX reader import report, or target stricter relationship transform provenance over package parts. Do not repeat raw reference inventory, relationship part load summaries, role target inventory, or role policy summaries.

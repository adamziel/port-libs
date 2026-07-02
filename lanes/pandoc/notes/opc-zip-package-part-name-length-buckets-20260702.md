# OPC ZIP package part name-length buckets

Hook: `plib-f9cul`, Pandoc shared ZIP/OPC package core blocker slice.

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`preflightZipCentralDirectoryManifest()` now carry metadata-only OPC package
part-name byte lengths and deterministic bucket rollups for package review.
Each valid package part entry exposes `partNameBytes`,
`packagePartNameLengthBucket`, and bucket bounds, while directories and invalid
part names remain outside the part-name buckets.

The package-level summaries expose ordered up-to-15, 16-to-63, 64-to-127, and
128-plus byte buckets with counts, entry names, part names, compressed and
uncompressed byte totals, ZIP source-record byte totals, role and handoff
counts, observed min/max lengths, and longest part-name provenance. The same
fields are available from constructed ZIP packages and raw central-directory
OPC preflights before XML package handoff.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 5373 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6061 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.

# OPC ZIP Manifest Package-Part Basenames

Slice: `plib-562cc`

`OpcRelationshipGraph` now carries metadata-only package-part basename rollups
through both constructed `ZipPackage` manifests and raw central-directory
manifest preflights before XML package graph construction.

- Each package-part entry records `packagePartBaseName` and
  `packagePartCaseFoldBaseName`.
- Manifest summaries expose basename counts, entry-name maps, ordered summary
  rows, duplicate basename groups, and duplicate case-folded basename groups.
- Raw central-directory preflights preserve parity with constructed package
  manifests, including duplicate `document.xml` package parts and case-folded
  media basename collisions such as `Logo.PNG`/`logo.png`.

The slice stays within native PHP package metadata review. It does not read OPC
payload bytes beyond existing manifest preflight metadata and does not invoke
Pandoc, office suites, zip/unzip tools, XML validators, or external services.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

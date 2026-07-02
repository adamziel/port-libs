# ZIP package manifest package-comment length buckets

Date: 2026-07-02
Slice: `plib-1oizo`

`ZipPackage::packageManifestPreflight()` now classifies ZIP comments by
metadata-only byte-length bucket in the shared ZIP/OPC package manifest:

- EOCD package comments expose `none`, `up-to-15-bytes`, `16-to-63-bytes`,
  `64-to-127-bytes`, and `128-plus-bytes` classes with explicit min/max byte
  bounds while retaining existing offset, hash, preview, and byte-exposure
  policy fields.
- Central-directory entry comments expose ordered length-bucket rollups with
  entry/file/directory counts, raw comment bytes, review/source byte totals,
  directory roots, extension keys, compression methods, entry names, and largest
  comment records.

`OpcRelationshipGraph` raw central-directory package-source preflights preserve
the same package-comment bucket fields so constructed `ZipPackage` handoff and
raw OPC manifest handoff remain shape-identical.

The focused ZIP fixture covers both empty and present package comments, verifies
constructed and raw strict package-manifest parity, and asserts entry-comment
length bucket rollups for central-directory review fields.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
`zip`/`unzip`, validators, or live services were invoked.

2026-07-01 plib-o2w5j

Shared ZIP/OPC package preflights now carry metadata-only entry-comment
provenance rollups:

- `ZipPackage::packageManifestPreflight()` groups comment-bearing entries by
  directory root and package-part extension, including raw comment bytes,
  central directory record bytes, central review-field bytes, source-record
  bytes, and entry names.
- `OpcRelationshipGraph::preflightZipEntryManifest()` and
  `preflightZipCentralDirectoryManifest()` project the same comment provenance
  through OPC role and handoff-kind summaries for direct package and raw central
  directory handoffs.
- Byte exposure remains metadata-only: comment hashes, byte counts, offsets, and
  bounded provenance are exposed, but raw comment bytes are not.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with 2 files, 11,422 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/*Zip*Test.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with 32 files, 14,236 assertions, and 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests` was captured in
  `/tmp/plib-o2w5j-full-pandoc.log` and remains baseline-red with 534 files,
  142,322 assertions, and 8,912 failures.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, external validators, or live services were invoked.

# ZIP package manifest file attribute rollups

Date: 2026-07-02
Bead: plib-caahw

`ZipPackage::packageManifestPreflight()` now carries metadata-only central-directory file attribute provenance in the shared ZIP package manifest. Each manifest entry includes external file attributes, DOS low-byte attributes, internal file attributes, hex forms, decoded attribute names, and review booleans for DOS hidden/system/volume-label and internal text/unknown bits.

The manifest also exposes deterministic package-level rollups keyed by full external file attributes, DOS attributes, and internal file attributes, plus review entry lists for DOS hidden/system/volume-label entries and internal file attributes. These fields are included in the manifest hash payload so DOCX/ODF/OPC readers can compare package attribute classes without rewalking raw central-directory records or exposing payload bytes.

Focused fixture coverage was added in `ZipPackageTest.php` for read-only/archive, hidden/archive, directory, text-internal, and unknown-internal attribute combinations. The existing deterministic and data-descriptor manifest hash fixtures were extended to account for the new payload fields.

Validation:
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` (1 file, 6,150 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` (1 file, 5,333 assertions, 0 failures)

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, fetchers, or live services were invoked.

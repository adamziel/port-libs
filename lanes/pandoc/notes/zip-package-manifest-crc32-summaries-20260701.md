# ZIP package manifest CRC32 summaries

Slice: `plib-xawom`

`ZipPackage::packageManifestPreflight()` now has direct shared ZIP coverage for
metadata-only CRC32 fingerprint rollups. The focused fixture records duplicate
CRC32 groups across stored and deflated package parts, including a streamed
data-descriptor entry, stored media duplicates, an empty directory, and a
unique core-properties part.

The mapped case asserts package-level CRC32 summary counts, duplicate CRC32
hexes, duplicate entry totals, per-fingerprint file/directory counts,
compressed and uncompressed byte totals, local/source-record byte totals,
data-descriptor counts, directory roots, compression methods, and entry names.
The same summaries are verified through constructed-package, strict import, and
raw strict import preflight handoffs without exposing package payload bytes.

Validation:

- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6083 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.

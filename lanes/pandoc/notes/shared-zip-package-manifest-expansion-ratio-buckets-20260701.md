# Shared ZIP package manifest expansion-ratio buckets

Hook: `plib-7ptd6`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::packageManifestPreflight()` now reports metadata-only expansion
ratio provenance for package-manifest handoff. Each manifest entry carries its
derived expansion ratio, and the manifest aggregates zero-byte, up-to-1x,
1x-to-10x, 10x-to-100x, over-100x, and unknown-ratio buckets with entry names,
file/directory counts, byte totals, and largest-ratio provenance.

The derived expansion metadata is intentionally excluded from the deterministic
`zip-package-manifest-v1` JSON hash payload, preserving the existing manifest
identity contract while giving DOCX/EPUB/ODT package readers a bounded review
surface for compressed package expansion risk.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6234 assertions, 0 failures`

No Pandoc, office suite, TeX/browser/Typst engine, `zip`/`unzip`, ZipArchive,
external validator, Node tooling, Jupyter, online service, or live provider
test was invoked.

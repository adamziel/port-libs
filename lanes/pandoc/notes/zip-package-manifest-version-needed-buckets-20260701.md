# ZIP package manifest version-needed buckets

Slice: `plib-sz1r1`

`ZipPackage::packageManifestPreflight()` now carries deterministic ZIP
extraction-version provenance in the shared package manifest:

- package-level `versionNeededToExtract` buckets with entry counts, byte totals,
  data-descriptor totals, minimum feature-version sets, compression methods,
  and entry names;
- per-entry local/central version-needed parity, minimum feature-version
  metadata, bounded-reader overflow flags, and feature-minimum checks;
- compact manifest-hash coverage for the per-entry version-needed fields.

The focused fixture mixes stored v10 entries with deflated and data-descriptor
v20 entries, then asserts constructed-package and raw strict package-manifest
parity. The implementation stays inside bounded native PHP ZIP/OPC metadata and
does not invoke external ZIP tools, Pandoc, office suites, TeX/browser engines,
Jupyter, Node tooling, live services, or external validators.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

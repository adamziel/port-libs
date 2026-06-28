# Pandoc ODF compact manifest size diagnostics

Bead: `plib-pli83`

This slice aligns compact `OpenDocumentPackage` manifest-size handling with the
rich ODF reader path. Malformed `manifest:size` values no longer abort ODT
package ingestion before review metadata is available. Compact manifest entries
now preserve the raw size token, valid/invalid flags, and the
`odf-manifest-invalid-declared-size` diagnostic while keeping normalized
`size`/`declaredSize` integers for valid values.

The change remains metadata-only:

- invalid declared sizes do not fetch or expose additional bytes;
- invalid sizes do not produce declared-size mismatch diagnostics;
- compact media, manifest review, inventory, identity, core handoff, and
  document-part version summaries retain declared-size provenance.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 1 file, 2,097 assertions, 0 failures.

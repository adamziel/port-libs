# pandoc-shared-zip-package-core-current-base-20260608T123129Z

## Scope

Added strict ZIP import handoff coverage for central-directory inventory metadata.
`ZipPackage::strictImportPreflight()` now embeds the existing
`centralDirectoryInventoryPreflight()` summary under `centralDirectoryInventory`,
including scanned entry counts, record offsets, bounded-reader issues, and
central-directory digital-signature provenance.

This keeps the static inventory primitive as the source of truth and makes the
strict DOCX/ODT/EPUB package-readiness summary complete enough for WordPress
review packets without running Pandoc, zip/unzip, ZipArchive, Word, LibreOffice,
or external archive tools.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before edits: `1 test files, 1500 assertions, 0 failures`
- Focused: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result after edits: `1 test files, 1511 assertions, 0 failures`
  - New named PASS: `embeds zip central directory inventory in strict package import preflight`
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors

## Non-Overlap

This does not repeat the accepted static central-directory inventory parser,
archive-extra-data record rejection, ZIP64 package/extra-field rejection, split
archive preflight, local-header order provenance, name hygiene checks,
platform metadata sidecar handling, invalid DOS timestamp preflight, or ZIP
payload integrity slices. The change is limited to strict import composition
and WordPress smoke evidence.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`ZipPackage::centralDirectoryInventoryPreflight()` primitive and the current
strict import/read-integrity pipeline. Full upstream Pandoc runner parity and
external archive validation remain out of scope for this isolated lane worker.

## Next

A useful follow-up would add a raw-bytes strict package preflight wrapper for
unsupported packages that cannot be instantiated by `ZipPackage::fromString()`,
while still reusing the existing bounded ZIP64, split-archive, archive-extra,
encryption, and compression policy primitives.

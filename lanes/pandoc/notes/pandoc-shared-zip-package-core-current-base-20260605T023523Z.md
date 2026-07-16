# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T023523Z`

Base accepted HEAD: `e9802384b8f60cb6c622eaa238aa0dc23f4d6e07`

## Implementation

- Added bounded ZIP general-purpose flag preflight for encrypted package
  metadata before DOCX/EPUB/ODT package parts are exposed.
- `ZipPackage::fromString()` now rejects central-directory entries with:
  - bit 0 traditional encryption;
  - bit 6 strong encryption;
  - bit 13 central-directory encrypted / local-header metadata masking.
- Preserved accepted non-encrypted UTF-8, data-descriptor, local-header,
  ZIP64, symlink, directory-payload, NTFS timestamp, and Unicode path/comment
  behavior.
- Updated the WordPress ZIP package preflight example so strong-encryption and
  central-directory encrypted metadata are classified as rejected before media
  import.

## Source Truth

The ZIP APPNOTE general-purpose bit flags mark encrypted entry data, strong
encryption, and central-directory encryption/local-header masking. This bounded
package reader does not implement ZIP decryption or central-directory
decryption, so those flags must reject during package preflight rather than
allowing higher-level document readers to trust masked metadata.

Reference checked: `https://support.pkware.com/pkzip/appnote`.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
ZipArchive, TeX/PDF engine, external template engine, browser renderer, media
player, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 225 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 225 assertions, 1 failures`.
  - Failure: strong-encryption ZIP metadata was accepted before import
    preflight.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 227 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-20260605T023523Z.out; status=$?; printf 'pass_lines='; rg -c '^PASS ' /tmp/pandoc-lane-tests-20260605T023523Z.out || true; tail -n 1 /tmp/pandoc-lane-tests-20260605T023523Z.out; exit $status`
  - Result: `pass_lines=543`, `19 test files, 5807 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` central-directory parser, local-header validation, and package
metadata model. It does not require `ZipArchive`, external `zip`/`unzip`,
Pandoc, Haskell runners, office tools, TeX/PDF engines, browser renderers, or
online services.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
validation for ordinary files, package writing, modified DOS timestamps,
extended timestamp reading/writing, NTFS timestamp parsing, Unicode path/comment
decoding, ZIP64 extra-field rejection, Unix symlink rejection, drive-letter path
rejection, directory-payload rejection, custom writer extra-field emission, OPC
content-types or relationship graph behavior, archive compression stream
handling, DOCX/ODT/EPUB body parsing, PDF engine handoff diagnostics, syntax
highlighting, doctemplate rendering, YAML metadata, CSL/BibTeX, table geometry,
math/TeX, charset/Unicode text helpers, XML/HTML DOM handling, or legacy DOC/CFB
slices. It only adds bounded ZIP encrypted metadata flag preflight.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archive decoding,
central-directory decryption, executable permission policy, filesystem
extraction policy, non-deflate compression methods, and broader malformed
package flag corpus coverage as separate bounded ZIP package slices if concrete
Pandoc fixtures require them.

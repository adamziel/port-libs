# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T033600Z`

Base accepted HEAD: `41762de6274c1435dd15943e6293756fd3806571`

## Implementation

- Added bounded ZIP local-entry layout preflight for DOCX/EPUB/ODT package
  containers before package entries are exposed to WordPress import review.
- `ZipPackage::fromString()` now scans every central-directory entry's local
  header signature and declared record span.
- The reader rejects compressed data ranges that overlap the next local header
  or central directory.
- The reader also rejects entries with the data-descriptor flag when the
  descriptor would overlap the next local header or central directory.
- Existing on-demand local/central metadata mismatch checks are preserved, so
  malformed local names, timestamps, CRCs, sizes, and extra fields still fail at
  the same read/local-extra access points covered by prior tests.
- Updated the WordPress ZIP package preflight example so overlapping local
  entry layouts are classified as rejected before media import.

## Source Truth

ZIP package readers rely on central-directory entries pointing at local file
headers whose file data and optional data descriptor occupy a non-overlapping
record before the next local header or the central directory. This bounded PHP
package reader does not extract to disk and does not implement recovery from
ambiguous local record layouts, so overlapping spans are rejected during
preflight.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
ZipArchive, TeX/PDF engine, external template engine, browser renderer,
archive tool, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 227 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 228 assertions, 1 failures`.
  - Failure: overlapping local-entry layout was accepted during package parse.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 232 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-final-20260605T033600Z.out; status=$?; printf 'pass_lines='; rg -c '^PASS ' /tmp/pandoc-lane-tests-final-20260605T033600Z.out || true; tail -n 1 /tmp/pandoc-lane-tests-final-20260605T033600Z.out; exit $status`
  - Result: `pass_lines=583`, `19 test files, 6516 assertions, 0 failures`.
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
`ZipPackage` central-directory parser, local-header range scanner, package
metadata model, CRC/DEFLATE paths, and WordPress ZIP preflight example. It does
not require `ZipArchive`, external `zip`/`unzip`, Pandoc, Haskell runners,
office tools, TeX/PDF engines, browser renderers, archive tools, or online
services.

## Non-Overlap

This does not repeat accepted central-directory parsing, ordinary local-header
read validation, data-descriptor value validation, package writing, modified
DOS timestamps, extended timestamp reading/writing, NTFS timestamp parsing,
Unicode path/comment decoding, ZIP64 extra-field rejection, Unix symlink
rejection, drive-letter path rejection, directory-payload rejection, ZIP
encryption flag preflight, custom writer extra-field emission, OPC
content-types or relationship graph behavior, archive compression stream
handling, DOCX/ODT/EPUB body parsing, PDF engine handoff diagnostics, syntax
highlighting, doctemplate rendering, YAML metadata, CSL/BibTeX, table
geometry, math/TeX, charset/Unicode text helpers, XML/HTML DOM handling, or
legacy DOC/CFB slices. It only adds bounded local-entry record-span preflight.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archive decoding,
central-directory decryption, executable permission policy, filesystem
extraction policy, non-deflate compression methods, and broader malformed
local-header corpus coverage as separate bounded ZIP package slices if
concrete Pandoc fixtures require them.

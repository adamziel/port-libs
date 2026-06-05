# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T081847Z`

Base accepted HEAD: `5b29437c18755ebdccc85cb18bd9d12031745925`

## Implementation

- `ZipPackage::fromString()` now runs full local-header integrity preflight for
  every central-directory entry during package construction.
- The eager preflight rejects local header name, version-needed, flags,
  compression method, DOS timestamp, CRC/size, local Unicode path,
  extended/NTFS timestamp, ZIP64 local extra-field, and data-descriptor
  mismatches before package entries are exposed to DOCX/EPUB/ODT readers.
- The WordPress ZIP package preflight smoke now reports local-header name
  mismatch rejection as an import policy.

## Source Truth

Pandoc package readers consume DOCX, EPUB, and ODT as ZIP-backed containers.
The central directory is the package inventory, but the corresponding local
header is the byte-level record used to locate entry payloads. If those two
records disagree, a bounded native package reader should reject the container
before exposing part names or attachment bytes to higher-level readers.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 281 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result after implementation before expectation updates: `1 test files,
    260 assertions, 7 failures`, all because malformed local metadata was now
    rejected during `ZipPackage::fromString()`.
- Focused `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 283 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP PASS cases: `41 -> 42`, adding 1 PASS case.
- Focused ZIP assertions: `281 -> 283`, adding 2 assertions.
- Manifest mapped checks: `1229 -> 1230`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 133`.
- Lane PHP pass count: `770 -> 771`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives, in-process CRC/DEFLATE handling,
and the accepted ZIP/OPC package support row. Full upstream Pandoc runner
parity remains blocked on hydrating/building the Haskell Pandoc checkout at the
manifest commit, but local-header integrity preflight is not blocked by that
runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing,
local entry order exposure, data descriptors, CRC/local-header lazy read
checks, central/local extra-field parsing, extended or NTFS timestamp metadata,
ZIP64 extra-field rejection, Unix symlink rejection, raw/decoded unsafe path
rejection, directory payload rejection, local-entry overlap rejection,
duplicate local-header-offset rejection, central-directory tail rejection,
aggregate size preflight, ZIP version-needed exposure, bounded per-entry
reads, gzip/tar/LZ4 archive streams, OPC relationships/content types,
DOCX/ODT/EPUB readers, syntax highlighting, table geometry, math/TeX,
doctemplates, or Markdown/HTML reader and writer behavior. It only moves local
header integrity checks to package construction so malformed containers are
rejected before names or bytes are exposed.

## Follow-Up

Keep AES/encrypted archive payload support, spanning archives, verified
central-directory signatures, full ZIP64 large-archive support, hidden local
slack/prefix policy, and additional compression methods as separate bounded
ZIP package slices.

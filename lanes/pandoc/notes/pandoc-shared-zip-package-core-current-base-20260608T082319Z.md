# pandoc-shared-zip-package-core-current-base-20260608T082319Z

Accepted base: `6c29be4bda70f43b52fe8fb02b6dc807643e8db3`

## Slice

Implemented bounded ZIP package support for Info-ZIP Unix UID/GID owner extra
fields (`0x7875`) without using Pandoc, Word, LibreOffice, `zip`/`unzip`,
`ZipArchive`, Cabal, Haskell runners, online services, live provider tests, or
live-service provider tests.

The patch keeps ownership under `lanes/pandoc/**` and focuses only on ZIP/OPC
package primitives:

- `ZipPackageEntry` parses and validates the version-1 variable-length
  little-endian UID/GID payload.
- `ZipPackage` exposes central/local owner preflight summaries and blocks owner
  metadata in strict import preflight before Office/EPUB/ODT media handoff.
- Generated ZIP package output rejects owner extra fields before writing, so
  local filesystem owner provenance is not emitted accidentally.
- The WordPress ZIP preflight example reports the owner policy and guards it in
  `--self-test`.

## Non-Overlap

This avoids the already accepted ZIP clusters for central-directory signatures,
trailing deflate consumption, invalid DOS timestamps, Unicode-normalized name
collisions, ZIP64 extra-field rejection, WinZip AES extra-field rejection,
NTFS timestamps, executable/symlink/special-file permission preflight, and
central/local extra-field duplicate or mismatch summaries.

## Evidence

Focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1354 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`

Required finish checks:

- `php -l` for changed PHP files.
  - Result: no syntax errors in `ZipPackageEntry.php`, `ZipPackage.php`,
    `ZipPackageTest.php`, and `wordpress-zip-package-preflight.php`.
- `php -r` JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`.
  - Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`ZipPackage`/`ZipPackageEntry` reader/writer, strict import preflight, and
lane-local ZIP fixture builder.

## Next

For ZIP package follow-up, choose a non-overlapping native package primitive
such as central-directory extra-field policy for another bounded import risk,
OPC ZIP package consistency, or package payload integrity. Do not execute
Pandoc, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, Cabal/Haskell runners,
external archive tools, online services, live provider tests, or live-service
provider tests.

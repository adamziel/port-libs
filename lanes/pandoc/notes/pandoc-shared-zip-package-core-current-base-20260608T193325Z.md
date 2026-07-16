# pandoc-shared-zip-package-core-current-base-20260608T193325Z

Accepted base: `13ef792b9726ca74a5372ce5b45a701d4366670c`

## Scope

Implemented bounded ZIP EOCD candidate disambiguation for package comments that contain EOCD-looking signature bytes (`PK\005\006`). Native `ZipPackage` now ignores an EOCD candidate unless its declared central-directory byte span is coherent, so DOCX/EPUB/ODT package comments can carry arbitrary bytes without making strict import lock onto a false EOCD inside the comment.

The plausibility check validates the central-directory byte layout while preserving existing diagnostics for declared entry-count mismatches, archive extra data records, central-directory digital signatures, and ZIP64 package records.

## Red-first Evidence

- One-off in-memory package before the implementation:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\ZipPackage; $zip = ZipPackage::build([["name"=>"word/document.xml", "data"=>"<w:document/>", "compressionMethod"=>0]], "PK\x05\x06" . str_repeat("\0", 18)); try { $package = ZipPackage::fromString($zip); echo "ok ".$package->packageComment()."\n"; } catch (Throwable $e) { echo "fail: ".$e->getMessage()."\n"; }'`
- Result before patch: `fail: Unexpected ZIP bytes between the central directory and end-of-central-directory record`

## Verification

- Baseline focused test before patch: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1672 assertions, 0 failures`
- First full focused run after patch exposed one existing malformed-count fixture regression:
  - Result: `1 test files, 1670 assertions, 1 failures`
  - Fixed by validating the central-directory byte span independently from the declared entry count.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1684 assertions, 0 failures`

No example smoke was added or updated for this slice.

## Mapping Delta

- Mapped upstream/static support denominator: `2166 -> 2167`
- ZIP package core support cases: `22 -> 23`
- ZIP package core assertions: `161 -> 173`
- `phpPass` is unchanged because this adds assertions inside the existing ZIP package PHP test file.

## Dependency Closure

No new native PHP support component is needed. This reuses `ZipPackage` EOCD, central-directory, package-comment, raw strict-import, and focused ZIP package test primitives.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted ZIP slices for central-directory signatures, trailing deflate bytes, Unicode name collisions, invalid DOS timestamps, data-descriptor provenance, local-header name/metadata spoofing, split archive disk markers, ZIP64 rejection, AES/encryption rejection, duplicate extra-field IDs, NTFS timestamps, or malformed Info-ZIP Unicode metadata. The slice only covers EOCD candidate selection when a valid ZIP package comment contains EOCD-looking bytes.

## Follow-up

For ZIP package follow-up, choose a non-overlapping native package primitive such as bounded ZIP64 local/central compatibility preflight or OPC/package cross-checks without executing Pandoc, external archive tools, office tools, online services, or Haskell runners.

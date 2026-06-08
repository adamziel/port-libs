# pandoc shared ZIP package core current-base 20260608T214547Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T214547Z`
- Accepted base: `de56150306796ff6c39d1f6214abe62da3666962`
- Implemented one bounded ZIP package primitive: generated package comments and generated entry comments now reject raw C0/DEL control bytes before ZIP bytes are emitted, matching strict Office/EPUB/ODT media handoff policy while preserving raw third-party archive preflight diagnostics.

## Behavior

- Added writer-side comment control-byte validation in `ZipPackage::build()` for package comments and central-directory entry comments.
- Kept imported/raw ZIP behavior intact: archives containing raw comment control bytes remain instantiable for review, `commentPreflight()` exposes offsets and issues, and `strictImportPreflight()` rejects them with `comment-control-bytes`.
- Updated the WordPress ZIP package preflight smoke so raw control-byte comments are built with a lane-local ZIP fixture while generated `ZipPackage::fromParts()` comments with those bytes are rejected before writing.

## Verification

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` notes existed for this lane.
- Red check: `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\ZipPackage; $p = ZipPackage::fromParts([["name"=>"word/document.xml","data"=>"ok","comment"=>"entry\x7fcomment"]], "source\0package"); echo $p->packageComment(), "\n", $p->entry("word/document.xml")->comment, "\n";'`
  - Result before fix: generated package and entry comments containing raw control bytes were accepted.
- PHP lint:
  - `php -l lanes/pandoc/src/ZipPackage.php`: no syntax errors
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`: no syntax errors
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1894 assertions, 0 failures`
  - Delta: `+12` focused assertions in the existing ZIP package test file
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- Root harness: not run - isolated micro-slice

## Dependency Closure

- No new support component is needed. The slice reuses native `ZipPackage` comment preflight, strict import preflight, and in-memory ZIP package writer behavior.
- No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`/`unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was run.

## Non-overlap

- Avoided accepted ZIP package coverage for raw comment control-byte import diagnostics, package/comment visibility policy, central-directory signatures, invalid DOS timestamps, Unicode/case-insensitive name collisions, split archive markers, creator-host metadata, permission policy, encryption/AES policy, ZIP64 accounting, extra-field mismatch policy, and trailing deflate payload integrity.
- Follow-up should choose a distinct ZIP/OPC package primitive, such as additional data-descriptor layout provenance, central-directory archive records, or a separate generated-package metadata policy edge.

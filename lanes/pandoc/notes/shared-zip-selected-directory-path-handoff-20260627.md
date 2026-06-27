# Shared ZIP Selected Directory Path Handoff

Scope: shared ZIP/OPC package primitives under `lanes/pandoc`.

Implemented a bounded metadata-only handoff slice in `ZipPackage::entryHandoffPreflight()`:

- Each present selected entry now carries `directoryPath`, preserving the full parent package path such as `/`, `customXml/`, `word/_rels/`, `word/media/`, and `word/embeddings/`.
- The selected and readable subsets now include `selectedDirectoryPathSummaries` and `handoffDirectoryPathSummaries` with per-path entry counts, file/directory counts, byte totals, roles, directory roots, path depth, and entry names.
- Oversized blocked entries stay visible in the selected directory-path bucket while staying absent from readable handoff buckets, so DOCX/EPUB/ODT readers can review embedded-package/media paths before exposing bytes.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result after rebasing with the adjacent package-kind slice: 1 file, 4,994 assertions, 0 failures.

Non-goals:

- Did not invoke Pandoc, office suites, `zip`/`unzip`, external validators, browser engines, TeX/PDF/Typst engines, Node tooling, or network services.
- Does not repeat accepted selected-entry directory-root, extension-bucket, order, path-depth, raw-name/comment, extra-field, platform-attribute, fixed-header, data-descriptor, source-byte-span, zero-byte, or expansion summaries.

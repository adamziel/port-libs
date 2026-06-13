# Pandoc EPUB nav/NCX label provenance

Implemented a bounded compact EPUB package reader slice for label provenance in
`EpubPackageReader`.

`EpubPackageReader` now attaches `labelProvenance` to EPUB XHTML nav entries and
NCX `navPoint` entries. The review packet preserves the label source element,
normalized label text, language, direction, raw attributes, `epub:type` tokens,
and image-label contributors with `src`, resolved package path, fragment, alt,
title, and raw image attributes.

The focused fixture now includes an XHTML nav label with source attributes and a
local image-label contributor, plus an NCX `navLabel`/`text` pair with source
attributes. Existing label strings, href/path/fragment resolution, child entries,
and page-list/landmarks behavior remain unchanged.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`: 1 file, 235 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 75838 assertions, 0 failures.

Lane accounting:

- Added one mapped compact EPUB package case: `mappedEpubNavNcxLabelProvenanceCases = 1`.
- Added 22 focused assertions: `epubNavNcxLabelProvenanceAssertions = 22`.
- Moved `phpPass` from 3363 to 3364 after rebasing on the HTML image-map association diagnostics slice; `phpFail` remains 0.
- Moved mapped upstream cases from 3323 to 3324.

No Pandoc, EPUBCheck, zip/unzip, browser renderer, Node tooling, online service,
live provider test, or external validator was invoked.

Non-overlap: this does not repeat the earlier full `EpubReader` NCX
`navPoint`/`navList` source attribute work, NCX label audio provenance, or NCX
`docTitle`/`docAuthor` audio provenance. This slice is restricted to compact
`EpubPackageReader` review metadata for XHTML nav labels and NCX `navPoint`
labels.

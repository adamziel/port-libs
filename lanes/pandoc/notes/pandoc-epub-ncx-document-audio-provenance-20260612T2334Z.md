# EPUB3 NCX Document Metadata Audio Provenance

Date: 2026-06-12 UTC
Bead: plib-q073m

## Scope

The EPUB3 closure check was rebased after `PANDOC_STATUS.md` landed, so the shipping call uses `progress.md`, `PANDOC_STATUS.md`, `lanes/pandoc/lane-status.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, the accepted static upstream inventory, and local native PHP tests.

This slice closes one bounded native PHP gap under `lanes/pandoc`: NCX `docTitle` and `docAuthor` entries now preserve text attributes and audio-label provenance for package review. The new coverage includes local audio hashes, manifest metadata, missing local audio diagnostics, remote audio diagnostics, and aggregate `audioLabelReport` scope counts.

## Ship Call

| Surface | Evidence | Verdict |
| --- | --- | --- |
| EPUB3 package reader | 59 local passing EPUB evidence cases over 9 static upstream EPUB denominator cases. | Partial, not shippable. |
| New slice | One focused EpubReader case with 39 assertions for NCX document metadata audio provenance. | Covered. |
| Remaining gaps | Broader direct EPUB package reader parity and upstream Pandoc runner parity remain incomplete. External validators were not used. | Keep open. |

## Verification

Commands used:

```sh
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Expected local results for the final gate after rebase: focused EpubReaderTest passes 1 file, 4276 assertions, 0 failures; full `lanes/pandoc/tests` passes 44 files, 73896 assertions, 0 failures.

No Pandoc binary, EPUBCheck, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was invoked.

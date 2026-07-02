# EndNote title review metadata import

`plib-aw04w` recovery slice, 2026-07-02.

EndNote XML title packets now map translated, reviewed, and original title metadata into the same normalized CSL review fields already used by direct CSL JSON and RIS imports. The importer preserves canonical precedence over legacy aliases while keeping all raw EndNote title fields visible in `rawEndnoteXml.titleFields` and `titleVariantSummary`.

Focused verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

No external Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, office suite, TeX, zip/unzip, online service, live provider, or external validator was invoked.

# Pandoc CSL CamelCase Creator Aliases Current Base 20260610T175747Z

## Scope

- Bead: plib-ycgn, Pandoc citation/bibliography CSL core blocker slice.
- Current-base change: direct CSL item normalization now accepts camelCase creator-role aliases for `eventOrganizer`, `originalAuthor`, `editorialDirector`, and `reviewedAuthor`.
- Handoff behavior: the aliases populate the same normalized name lists as `event-organizer`, `original-author`, `editorial-director`, and `reviewed-author`, so CSL `<if variable>`, `<names variable>`, citation rendering, bibliography rendering, and WordPress block output all see the creators without requiring external citeproc tooling.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed 1 file / 4357 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 60927 assertions / 0 failures.

No Pandoc binary, citeproc, bibliography manager, Cabal/Haskell runner, office suite, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

# JSON/Native Link Raw Block-Container Mixed Content

Slice: `plib-ftnaq` on 2026-07-01.

`PandocJsonNativeAstTest.php` now covers mixed `Link`, `RawInline`, and `RawBlock` payloads adjacent to nested `BlockQuote`, `Div`, and `Note` block containers.

The fixture verifies JSON and native writers flush leading and trailing inline runs into valid `Plain` blocks around raw block payloads and nested block containers, then checks JSON/native reader round trips preserve link targets, raw inline payloads, generic raw block payloads, and block-only container child lists.

The validation gate also keeps current WordPress/citation handoff expectations green by preserving safe `xml:lang` attributes and avoiding duplicate prefixes when missing citation source text already includes the prefix. No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, Node tooling, online services, live providers, or external validators were invoked.

Focused validation:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> `1 test file, 6203 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorBiblatexPrimaryClassArchiveTest.php` -> `3 test files, 12428 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- conflict-marker scan of changed Pandoc lane files

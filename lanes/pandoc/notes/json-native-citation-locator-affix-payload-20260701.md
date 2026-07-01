# JSON Native Citation Locator Affix Payload - 2026-07-01

- Added focused `PandocJsonNativeAstTest` coverage for grouped citation records where only one citation is edited after JSON/native readback.
- The new case changes the edited citation prefix, locator-derived suffix label/value, author-in-text mode, note number, and hash while preserving the unchanged citation record's `reviewQueue` and `sourceOrdinal` sidecars.
- The edited record regenerates plain `citationPrefix`, `citationSuffix`, `citationMode`, `citationNoteNum`, and `citationHash` payloads and drops stale edited `reviewQueue`/`sourceOrdinal` sidecars in both `PandocJsonWriter` and `NativeWriter`.
- Isolated coverage passed with `1 selected test, 56 assertions, 0 failures`; full `PandocJsonNativeAstTest.php` remains baseline-red outside this slice with 1 file, 6127 assertions, 6 failures in existing raw alias, WordPress HTML sanitizer/attribute, figure caption, and CSL rendering expectations.
- Full `php tools/run-tests.php lanes/pandoc/tests` was attempted on 2026-07-01 and remains baseline-red outside this slice: 534 test files, 142350 assertions, 8912 failures, starting in existing DocBook, HTML/WordPress writer, LaTeX writer, and Markdown raw-block surge coverage.
- No external Pandoc, Haskell, Node, browser, validator, online service, or external citation/conversion tool was invoked.

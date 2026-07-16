# Charset Unicode Width: IBM855/CP855 DOS Cyrillic

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T233931Z`
Accepted base: `475b85e029e16dfc514361ae0145c8d6dab388cb`

Implemented one bounded native charset/Unicode support-library case: IBM855/CP855 DOS Cyrillic source-byte decoding in `UnicodeText`, including aliases `cp855`, `ibm855`, `dos855`, `xcp855`, `oem855`, and `csibm855`.

Source truth:
- Local Tcl encoding table `/usr/share/tcl9.0/encoding/cp855.enc` for the single-byte CP855 mapping.
- Existing lane charset handoff contract in `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, and `wordpress-charset-unicode-handoff.php`.

Focused evidence:
- Baseline `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`: `1 test files, 1138 assertions, 0 failures`.
- Red-first same command: `1 test files, 1139 assertions, 1 failures`; `cp855` decoded as `utf-8-repaired`.
- Final same command: `1 test files, 1152 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`: `charset unicode handoff self-test ok`.

Dependency closure:
- No new support component is needed. This reuses native PHP `UnicodeText` single-byte decoding, `MarkdownReader` sourceEncoding provenance, `WordPressBlockWriter`, and the existing charset handoff example.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

Next non-overlapping task:
- Cover another legacy byte decoder or display-width edge not already covered by IBM855, IBM866, IBM860, IBM775, IBM864, Windows/ISO/Koi8/Mac Cyrillic, CJK multibyte, Indic/Southeast Asian clusters, or Unicode separator controls.

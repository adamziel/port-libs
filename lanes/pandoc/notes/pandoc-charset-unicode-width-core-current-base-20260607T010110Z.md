# Pandoc Charset/Unicode Width Core - ISO-8859-9 Latin-5 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T010110Z`
Accepted base: `1acd0b30963f5e811b7ec3425d9875fee00e2067`

## Behavior

- Added bounded ISO-8859-9 / Latin-5 label recognition in `UnicodeText`.
- Decoded the six Latin-5 Turkish replacement byte slots to Unicode:
  `0xD0 -> U+011E`, `0xDD -> U+0130`, `0xDE -> U+015E`,
  `0xF0 -> U+011F`, `0xFD -> U+0131`, and `0xFE -> U+015F`.
- Preserved canonical `sourceEncoding` metadata and display-width accounting
  through Markdown parsing and WordPress block handoff.
- Extended the WordPress charset handoff smoke with an ISO-8859-9 audit row.

## Source Truth

This slice follows the bounded ISO/IEC 8859-9 Latin-5 layout needed by Pandoc
readers for Turkish legacy source bytes. It reuses the existing single-byte
decoder pattern: ASCII and shared Latin-1 bytes pass through unchanged, while
the Turkish-specific byte slots override ISO-8859-1 positions. Undefined-byte
repair behavior is unchanged.

No generated charset indexes, external charset converters, Pandoc executable,
Cabal/Haskell runner, browser renderer, online service, live provider test, or
live-service provider test was used.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 625 assertions, 0 failures`

Focused checks after edits:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 636 assertions, 0 failures`
- Delta: `+1` PHP PASS case, `+11` focused assertions

- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- Result: `charset unicode handoff self-test ok`

Final required checks:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, lane
test harness, and charset handoff example.

Full upstream Pandoc runner parity remains out of scope for this micro-slice
until a pinned upstream Pandoc checkout is hydrated and a non-mutating
Cabal/Haskell test plan is explicitly authorized.

## Non-Overlap

This patch does not overlap the already accepted UTF BOM/repair,
Windows-1252/1250/1251, ISO-8859-1/2/3/4/5/6/7/8/10/13/15, TIS-620, MacRoman,
KOI8-R, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR,
HZ-GB-2312, Unicode normalization, or display-width cluster slices. It also
does not touch DOCX, EPUB, ODT, CSL, YAML, table geometry, PDF handoff,
syntax-highlighting, ZIP/OPC, or upstream-runner dependency behavior.

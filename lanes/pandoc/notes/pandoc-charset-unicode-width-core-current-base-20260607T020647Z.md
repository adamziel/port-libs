# Pandoc Charset/Unicode Width Core - Windows-1253 Greek Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T020647Z`
Accepted base: `beafb5b9ebe55f9aec9402f03ec049292424d83f`

## Behavior

- Added bounded Windows-1253 / CP1253 label recognition in `UnicodeText`.
- Decoded Greek letters plus Windows smart punctuation, Euro, and Greek tonos
  byte slots before Markdown parsing and WordPress block handoff.
- Preserved undefined Windows-1253 byte slots as U+FFFD repair diagnostics,
  including unmapped C1 controls and the undefined D2/FF Greek table slots.
- Extended the WordPress charset handoff smoke with a Windows-1253 audit row
  carrying canonical source encoding and narrow/wide display-width evidence.

## Source Truth

This slice follows the bounded Windows-1253 Greek single-byte layout needed by
Pandoc readers for legacy Greek source bytes. It reuses the existing native
single-byte decoder pattern: Windows C1 punctuation is handled explicitly,
undefined slots repair to U+FFFD, shared Latin-1 punctuation passes through,
and Greek letter bytes reuse the already bounded ISO-8859-7 Greek table where
the two encodings share positions.

No generated charset indexes, external charset converters, Pandoc executable,
Cabal/Haskell runner, browser renderer, online service, live provider test, or
live-service provider test was used.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 636 assertions, 0 failures`

Red-first after adding the Windows-1253 focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: failed new case with expected encoding `windows-1253` and actual
  `utf-8-repaired`

Focused checks after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 650 assertions, 0 failures`
- Delta: `+1` PHP PASS case, `+14` focused assertions

- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- Result: `charset unicode handoff self-test ok`

Final required checks:

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check -- lanes/pandoc`

These final checks were run after this note was written and recorded in the
worker final response.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, lane test harness, and
charset handoff example.

Full upstream Pandoc runner parity remains out of scope for this micro-slice
until the pinned upstream Pandoc checkout is hydrated and Haskell/Cabal runner
work is explicitly authorized.

## Non-Overlap

This patch does not overlap the already accepted UTF BOM/repair,
Windows-1252/1250/1251, KOI8-R, ISO-8859-1/2/3/4/5/6/7/8/9/10/13/15,
TIS-620, MacRoman, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5,
GBK/GB18030, EUC-KR, HZ-GB-2312, Unicode normalization, display-width, emoji,
Indic, Myanmar/Khmer, or separator/control-width slices. It also does not touch
DOCX, EPUB, ODT, CSL, YAML, table geometry, PDF handoff, syntax-highlighting,
ZIP/OPC, archive-compression, or upstream-runner dependency behavior.

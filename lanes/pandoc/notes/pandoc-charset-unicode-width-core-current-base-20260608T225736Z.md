# Pandoc Charset/Unicode Width Core - IBM775/CP775 DOS Baltic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T225736Z`
Base accepted HEAD: `79f9f98965689b71a99ad50e1ab3f41478685bb2`

## Behavior

- Added bounded native IBM775/CP775/DOS775 label recognition to `UnicodeText`.
- Decodes the official CP775 Baltic high-byte table for legacy DOS Markdown imports, including Latvian and Lithuanian letters, Baltic quote bytes, box drawing, soft hyphen, and no-break space.
- Preserves canonical `ibm775` source encoding metadata and narrow/wide display-width audit rows through `MarkdownReader` and `WordPressBlockWriter`.

## Source Truth

- Official Unicode CP775 mapping: `https://www.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/PC/CP775.TXT`.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed for progress.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before the slice.
- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1108 assertions, 0 failures`.
- Red-first after adding the CP775 case before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1109 assertions, 1 failures` because `cp775` still normalized to `utf-8-repaired`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1122 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/UnicodeText.php`,
  `php -l lanes/pandoc/tests/UnicodeTextTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  reported no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f: valid\n"; }'`
  returned both lane JSON files as valid.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`
single-byte decoding and display-width helpers, `MarkdownReader`, the focused
Unicode tests, `WordPressBlockWriter`, and the existing WordPress charset
handoff example.

## Non-Overlap

This does not repeat accepted IBM437/850/852/857/860/861/862/863/865/866/869,
MacRoman/Mac Turkish/Mac Cyrillic/Mac Greek, ISO-8859-3/4/5/6/7/8/9/10/13/14/15/16,
Windows-1251/1253/1254/1255/1256/1257/1258, TIS-620/Windows-874,
Shift_JIS/EUC-JP/ISO-2022-JP, Big5/GBK/GB18030/EUC-KR/HZ-GB-2312, or the
recent Unicode display-width cluster slices. It is limited to the bounded
IBM775/CP775 DOS Baltic source-byte decoder plus WordPress charset audit
coverage.

## Next

Choose a non-overlapping charset or Unicode layout gap such as IBM855 Cyrillic,
CP864 Arabic, Mac Hebrew/Arabic, or additional Unicode line-break metadata.

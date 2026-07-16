# Pandoc Charset/Unicode Width Core - IBM857 DOS Turkish

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T214222Z`
Base: `a0d85bbfea71fbea16acdfcda87bce21bb3681b0`

## Behavior

- `UnicodeText::decodeBytes()` now recognizes `857`, `cp857`, `ibm857`,
  `dos857`, `xcp857`, `oem857`, and `csibm857` as the IBM857/DOS Turkish
  source encoding.
- The bounded single-byte decoder reuses the existing IBM850 table for shared
  box-drawing and Latin slots, overrides the IBM857 Turkish byte positions,
  and repairs undefined IBM857 bytes with replacement characters.
- The Markdown reader and WordPress charset handoff example now carry an
  IBM857 source row through decoded Turkish text, sourceEncoding metadata,
  WordPress block output, and default/wide display-width audit values.

## Source Truth

This ports the bounded support-library contract that source bytes labeled as a
legacy DOS Turkish code page must be decoded to Unicode before Markdown parsing
or WordPress block emission. The slice stays inside native PHP charset,
Unicode-repair, and display-width support; no Pandoc executable, Cabal
solver/build/test command, Haskell runner, external charset converter, browser
renderer, online service, live provider test, or live-service provider test was
used for progress.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before the
  slice.
- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1064 assertions, 0 failures`.
- Red-first after adding the IBM857 case:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1065 assertions, 1 failures` because `cp857`
  still fell back to `utf-8-repaired`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1080 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/UnicodeText.php`,
  `php -l lanes/pandoc/tests/UnicodeTextTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  reported no syntax errors.
- Lane JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  returned `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused Unicode tests, and the
existing WordPress charset Unicode handoff example.

## Non-Overlap

This does not repeat accepted BOM preflight, ISO-8859-3/7/8/9, Windows-1256,
Shift_JIS/Windows-31J, GBK/GB18030, HZ-GB-2312, Indic/Myanmar/Khmer display
cluster, or Unicode separator wrapping slices. The owned surface is the bounded
IBM857/DOS Turkish byte decoder plus display-width handoff audit.

## Next

Choose a non-overlapping charset or Unicode layout gap such as CP862
Hebrew/OEM source imports, Mac Turkish byte decoding, or bounded Unicode
line-break metadata.

# Pandoc Charset/Unicode Width Core - EUC-KR Korean Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T211217Z`
Base: `799387afcba3f6325103ae6aa05ab99d20a29761`

## Change

- Extended `UnicodeText::decodeBytes()` to recognize bounded EUC-KR labels
  including `euc-kr`, `ks_c_5601-1987`, `windows-949`, `cp949`, and `korean`.
- Added a fixture-backed native PHP EUC-KR decoder for Korean Markdown source
  bytes covering `한글 EUC-KR 테스트, 서울.` before Markdown parsing and
  WordPress block handoff.
- Kept malformed lead bytes, unmapped pairs, and truncated leads explicit as
  U+FFFD repair counts instead of silently treating the source as repaired
  UTF-8.
- Extended the WordPress charset handoff smoke with an `EUC-KR source` audit
  row carrying decoded text, canonical source encoding, and display width.

## Source Truth

The bounded source truth is the WHATWG Encoding Standard EUC-KR label family
and the KS X 1001/EUC-KR byte pairs needed by the fixture: `C7 D1` to U+D55C,
`B1 DB` to U+AE00, `C5 D7` to U+D14C, `BD BA` to U+C2A4, `C6 AE` to U+D2B8,
`BC AD` to U+C11C, and `BF EF` to U+C6B8. The slice intentionally does not
ingest the full generated index or implement Windows-949 extended pairs.

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset
converter, browser renderer, online sanitizer, online service, or live provider
test was executed.

## Red-First Evidence

Before implementation, a direct probe with the EUC-KR fixture bytes and the
`euc-kr` label returned `utf-8-repaired` with 10 replacement repairs:

`# �ѱ�\n\n�ѱ� EUC-KR �׽�Ʈ, ����.`

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 450 assertions, 0 failures`
  - Delta: +1 focused PASS case / +15 assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`
support path. Full generated WHATWG index ingestion, Windows-949 extension
pairs, HTML/XML charset sniffing, terminal-profile-specific emoji width
variants, and upstream Pandoc Haskell runner parity remain separate bounded
follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250, ISO-8859-2/15, MacRoman, Shift_JIS/Windows-31J, EUC-JP,
ISO-2022-JP, Big5, GBK, HZ-GB-2312, Unicode normalization, display-width
breakpoint splitting, emoji width, separator wrapping, default-ignorable and
format-control zero-width accounting, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, or upstream-runner
dependency audit slices.

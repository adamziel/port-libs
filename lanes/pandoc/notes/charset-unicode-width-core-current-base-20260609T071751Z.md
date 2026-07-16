# Charset Unicode Width Core Current Base - GB2312 Symbol Rows

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T071751Z`
Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Behavior

- Added bounded native GB2312/EUC-CN symbol-row mappings to `UnicodeText` for:
  - `A1 A1..A3` ideographic space/comma/full stop;
  - `A3 B0`, `A3 C1`, and `A3 E1` fullwidth digit/Latin samples;
  - `A4 A2`, `A4 A4`, and `A5 A2` hiragana/katakana samples;
  - `A6 A1` and `A6 C1` Greek alpha samples.
- Reused those selected pairs through GBK, GB12345, GB18030, ISO-2022-CN, and
  HZ-GB-2312 paths while preserving malformed-pair repair checks by moving the
  older "unmapped" probes from now-valid `A1 A1` to still-unmapped `A2 A1`.
- Threaded the decoded text through `MarkdownReader` sourceEncoding metadata,
  display-width accounting, `WordPressBlockWriter`, and the existing WordPress
  charset handoff audit table.

## Source Truth

The bounded source truth is the local static Tcl encoding inventory:

- `/usr/share/tcl9.0/encoding/gb2312.enc`
- `/usr/share/tcl9.0/encoding/euc-cn.enc`

No external charset converter was invoked.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1562 assertions, 0 failures
```

Red probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $bytes="# GB2312 Symbols\n\nSymbols \xA1\xA1\xA1\xA2\xA1\xA3; fullwidth \xA3\xC1\xA3\xE1\xA3\xB0; kana \xA4\xA2\xA4\xA4\xA5\xA2; greek \xA6\xA1\xA6\xC1."; $d=\PortLibs\Pandoc\UnicodeText::decodeBytes($bytes, "euc-cn"); var_export([$d["encoding"], $d["repairs"], $d["text"]]); echo "\n";'
array (
  0 => 'gbk',
  1 => 10,
  2 => '# GB2312 Symbols

Symbols ��。; fullwidth ���; kana ���; greek ��.',
)
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1578 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and
local static Tcl charset tables as source truth.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external converter, external
charset converter, TeX/PDF engine, browser renderer, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted UTF repair, UTF-16/UTF-32 BOM handling,
Windows/ISO/Mac/DOS single-byte decoders, Shift_JIS/Windows-31J, EUC-JP,
ISO-2022-JP, Big5 base/punctuation/kana/CP950, EUC-TW plane one,
GB1988/GB12345/GBK/GB18030 Chinese phrase and four-byte range coverage,
EUC-KR/Windows-949, ISO-2022-KR, ISO-2022-CN base GB2312 shift-state
coverage, HZ-GB-2312 base text, Unicode normalization, emoji/Indic/Thai/Lao
grapheme handling, East Asian ambiguous-width policy, separator wrapping, or
default-ignorable/prepended control width slices.

This patch is limited to a bounded GB2312/EUC-CN symbol-row mapping cluster and
its WordPress charset audit output under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset/Unicode gap such as additional source-backed
GB/CNS pairs, Big5-HKSCS extension mappings when a local source table is
available, or another display-width edge not already covered by current
charset slices.

# Pandoc Charset Unicode Width Core Current Base 20260606T092122Z

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T092122Z`
Base: `75e47bda11781d9e0c3af4331acfa9e1a02264e1`

## Behavior

- Tightened native ISO-2022-JP byte decoding in `UnicodeText::decodeIso2022Jp()`.
- If EOF is reached while the decoder is still in JIS X 0208, JIS Roman, or JIS Katakana state, the decoder now appends U+FFFD and increments `repairs`.
- Added a focused Markdown/WordPress handoff case proving truncated ISO-2022-JP source bytes preserve decoded text while exposing `repairs=1` in source-encoding metadata and the charset audit table.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, covering byte decoding, Unicode repair, and display-width behavior needed by Pandoc readers and writers. The bounded behavior matches the lane's existing stateful charset repair contract: malformed or incomplete legacy byte streams should stay reviewable but must not be reported as clean imports.

The upstream Pandoc runner was not executed. This is a native PHP support-library slice for byte decoding and WordPress review handoff.

## Red-First Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 541 assertions, 0 failures`.
- Red probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $r = PortLibs\Pandoc\UnicodeText::decodeBytes("# \x1B\$B\x37\x57\x32\x68\n", "iso-2022-jp"); var_export([$r["text"], $r["repairs"], $r["encoding"]]); echo "\n";'`
  - Result: decoded `# 計画\n` with `repairs=0`, so a stateful ISO-2022-JP source ending outside ASCII state was incorrectly treated as clean.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 549 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, the focused PHP test harness, and the WordPress charset Unicode handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not touch archive compression, ZIP/OPC, XML/HTML5 DOM, syntax highlighting, DOCX/ODF/EPUB3, CSL/BibTeX, YAML, doctemplates, math/TeX, PDF handoff planning, or legacy DOC/CFB behavior. It also does not repeat prior charset slices for ISO-8859-7 Greek, Shift_JIS/Windows-31J, GBK/HZ-GB-2312, emoji width, Indic/Myanmar/Khmer clusters, Unicode separators, or format-control display accounting.

## Follow-Up

Keep complete stateful charset mapping tables, full WHATWG Encoding Standard coverage, parser-level HTML/XML charset negotiation, locale-specific East Asian ambiguous-width policy, and full upstream-runner parity as separate bounded slices unless concrete fixtures require them.

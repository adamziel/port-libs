# Pandoc LaTeX Writer Unsupported Command Review Text

`LatexWriter` now renders unsupported raw/native command nodes as bounded,
escaped review text instead of dropping them from LaTeX output.

- Non-LaTeX `raw_block`, `raw_html`, `raw_inline`, and `raw_html_inline` nodes
  render as `\texttt{[unsupported ...]}` review labels.
- `native_block`, `native_inline`, and explicit `unsupported_command` nodes use
  constructor/command names plus bounded `reason`, `message`, source text,
  arguments, options, and attributes.
- Unsupported block commands preserve inline child payloads inside the quote
  block so reviewer-visible source hints are not lost.
- LaTeX/TeX raw block and inline nodes still pass through unchanged.

No Pandoc executable, Haskell/Cabal runner, TeX/PDF engine, browser renderer,
office suite, external validator, online service, or live provider test was
used.

Verification:

```bash
php -l lanes/pandoc/src/LatexWriter.php
php -l lanes/pandoc/tests/LatexWriterUnsupportedCommandTest.php
php tools/run-tests.php lanes/pandoc/tests/LatexWriterUnsupportedCommandTest.php
php tools/run-tests.php lanes/pandoc/tests/LatexWriterNativeInlineConstructorTest.php
```

The broader `lanes/pandoc/tests/LatexWriterTest.php` was already red on current
`origin/main`; after this slice its unsupported-command cases pass, with 14
remaining failures in unrelated LaTeX writer surfaces such as anchors, endnotes,
tables, list formatting, and line breaks.

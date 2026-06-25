# PlainMath Style, Text, and Enclosure Lane

## Scope

Worker: `plib-wj70q.4`

Owned files:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/tests/EpubWriterTest.php`

Controlling brief: `plainmath-supervisor-20260625.md`.

## Upstream Grounding

TexMath `Commands.hs` style operations at `styleOps` include these style
aliases beyond the PHP writer's existing baseline:

| TeX command family | TexMath text type | PHP MathML output |
| --- | --- | --- |
| `\mathup` | `TextNormal` | `mstyle mathvariant="normal"` |
| `\boldsymbol`, `\bm`, `\symbf`, `\mathbold`, `\pmb`, `\mathbfup` | `TextBold` | `mstyle mathvariant="bold"` |
| `\mathsfup` | `TextSansSerif` | `mstyle mathvariant="sans-serif"` |
| `\mathds` | `TextDoubleStruck` | `mstyle mathvariant="double-struck"` |
| `\mathscr` | `TextScript` | `mstyle mathvariant="script"` |
| `\mathbfit` | `TextBoldItalic` | `mstyle mathvariant="bold-italic"` |
| `\mathbfsfup` | `TextSansSerifBold` | `mstyle mathvariant="bold-sans-serif"` |
| `\mathbfsfit` | `TextSansSerifBoldItalic` | `mstyle mathvariant="sans-serif-bold-italic"` |
| `\mathbfscr`, `\mathbfcal` | `TextBoldScript` | `mstyle mathvariant="bold-script"` |
| `\mathbffrak` | `TextBoldFraktur` | `mstyle mathvariant="bold-fraktur"` |
| `\mathsfit` | `TextSansSerifItalic` | `mstyle mathvariant="sans-serif-italic"` |

The PHP writer now maps each of those commands and the EPUB fixture drives
each alias through generated MathML, not just table lookup.

## Existing Covered Surface

Existing focused EPUB MathML tests already cover:

- enclosures: `\boxed`, `\fbox`, `\cancel`, `\bcancel`, `\xcancel`
- color/background: `\textcolor`, `\color`, `\colorbox`
- phantom and padding: `\phantom`, `\hphantom`, `\vphantom`, `\smash`
- text mode: `\text`, `\mbox`, plus styled text commands through the style test
- named spacing: `\quad`, `\qquad`, `\thinspace`, `\enspace`, negative spaces
- operator names and named math operators: `\operatorname`, trig/log/limit families
- accents and over/under constructs in neighboring MathML fixtures

## Remaining Gaps

This slice does not claim full TexMath rendering parity for styled Unicode
conversion. TexMath rewrites many styled identifiers to mathematical Unicode
codepoints before writing MathML; the PHP path keeps the parsed child MathML
and applies `mathvariant` with `mstyle`.

The PHP text-mode path still treats `\text`, `\mbox`, and text-style groups as
balanced text payloads rather than recursively parsing inner TeX. That is
acceptable for the current EPUB MathML lane because it preserves XML-safe
`mtext` and original TeX annotations, but it is not a full TexMath text parser.

Arbitrary length spacing commands such as `\mspace{...}` and `\hspace{...}` are
not implemented in this lane; named spacing commands already have concrete
`mspace` coverage.

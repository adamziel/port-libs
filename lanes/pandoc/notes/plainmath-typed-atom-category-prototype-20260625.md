# PlainMath Typed Atom Category Prototype - 2026-06-25

Scope: narrow runtime prototype on the `plainmath-parity-20260625` base. This
branch's PlainMath path lives in `lanes/pandoc/src/HtmlWriter.php`; the target
base does not contain `lanes/pandoc/src/MathTexConverter.php` or
`PlainWriter.php`.

## Runtime Slice

- `HtmlWriter::plainMathAtomCategoryPrototype()` exposes diagnostic atom rows
  after the same PlainMath preprocessing used by `texMathToMathML()`.
- The prototype reports `source`, normalized `value`, TexMath-style `category`,
  and current `mathmlElement`.
- Covered categories are the requested `Ord`, `Bin`, `Rel`, `Open`, `Close`,
  `Pun`, and `Op` classes for identifiers, numbers, raw operators, named
  operators, mapped symbol commands, and `\left`/`\middle`/`\right` delimiters.
- The method is not called by `write()`, `renderMathML()`, EPUB generation, or
  the existing MathML string parser. Runtime MathML output stays unannotated.

## Upstream Grounding

TexMath's relevant model is `Text.TeXMath.Types.TeXSymbolType`, whose categories
include `Ord`, `Op`, `Bin`, `Rel`, `Open`, `Close`, and `Pun`. Its MathML writer
maps number and identifier expressions to `mn` and `mi`, named math operators to
normal-variant `mi`, open/close/fence symbols to `mo`, and other symbol classes
to `mo`.

The local prototype mirrors only that atom classification boundary. It does not
yet build a full TexMath-like `Exp` tree, perform `Bin` to `Ord` context
correction, insert invisible function-application operators, or implement atom
coercion commands such as `\mathop`, `\mathrel`, and `\mathbin`.

## Verification

- `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php` now carries
  optional `atomCategories` expectations for a mixed atom row and for
  `\left ... \middle ... \right` delimiters.
- `lanes/pandoc/tests/PlainMathConformanceTest.php` checks those expectations
  through `HtmlWriter::plainMathAtomCategoryPrototype()` and separately asserts
  that generated MathML does not receive diagnostic atom annotations.

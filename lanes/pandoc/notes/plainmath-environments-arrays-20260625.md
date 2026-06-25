# PlainMath Environments Arrays Lane

## Scope

Owned implementation surface:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/tests/HtmlWriterTest.php`

Upstream reference:

- `.upstream-cache/texmath/src/Text/TeXMath/Readers/TeX.hs`
- `.upstream-cache/texmath/src/Text/TeXMath/Writers/MathML.hs`

## Coverage Matrix

| Family | PHP MathML behavior | Notes |
| --- | --- | --- |
| `array`, `subarray` | Parses column specs into `mtable columnalign`, splits rows on `\\`, splits cells on top-level `&` | Ignores `|`, spaces, `@{...}`, `!{...}` and expands `*{n}{...}` column specs. |
| Matrix variants | Supports `matrix`, `smallmatrix`, `pmatrix`, `bmatrix`, `Bmatrix`, `vmatrix`, `Vmatrix` | Bracketed variants wrap the generated `mtable` in stretchy fence operators. |
| Alignment variants | Supports `align`, `align*`, `aligned`, `alignat`, `alignat*`, `alignedat`, `alignedat*`, `split`, `eqnarray`, `flalign`, `flaligned` | `alignat`/`alignedat` consume their pair-count group before reading rows. |
| Gather variants | Supports `gather`, `gather*`, `gathered`, `multline`, `multline*`, `multlined` | Rows render as centered table rows. |
| Cases variants | Supports `cases`, `dcases`, `rcases` | Cases use a left or right stretchy brace, matching the environment family. |
| Nested table environments | Top-level row/cell splitting tracks nested table-like `\begin`/`\end` pairs | Nested matrices inside cases remain one outer cell. |
| Line breaks | Consumes optional row spacing after `\\[1ex]` | The spacing is not represented in MathML, matching the bounded implementation goal. |
| Rule markers | Ignores `\hline`, `\hdashline`, `\cline{...}`, `\hhline{...}` | TexMath does not model these rules in its expression output. |
| Malformed known environments | Emits the remaining environment source as a single `mtext` and avoids partial `mtable` output | Keeps generated HTML/XML parseable. |

## Deliberate Gaps

- Layout details such as row spacing from `\\[1ex]`, array rule drawing, and `\arraystretch` are not represented.
- Full TeX package behavior for environment definitions and macro-expanded environment names is out of scope for this lane.
- MathML writer output uses compact table-level `columnalign` attributes where practical instead of reproducing TexMath's per-cell `columnalign` plus CSS style attributes.

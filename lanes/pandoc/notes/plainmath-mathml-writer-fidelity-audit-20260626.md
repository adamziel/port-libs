# PlainMath MathML Writer Fidelity Audit

Date: 2026-06-26 UTC
Bead: `plib-wj70q.17`

## Scope

Audit target: TexMath / PlainMath MathML writer fidelity after the current
parser work, specifically form attributes, per-cell alignment,
`style="text-align: ..."` metadata, and styled Unicode conversion.

This is a report-only lane. No runtime code was changed and no `knownGaps`
entry was updated. The repository does not have a single `knownGaps` file for
this lane; current gap tracking is distributed through Pandoc notes and lane
status text. This note is the gap matrix for the assigned slice.

## Source Truth

- Upstream TexMath HEAD checked with `git ls-remote`:
  `170899673ee31de9096e178605e8da31a36e4185`.
- Upstream Pandoc HEAD checked with `git ls-remote`:
  `c568a0e66599384fdabc08ad656fd5beeac39ae1`.
- Primary upstream files inspected:
  - `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Writers/MathML.hs`
  - `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Unicode/ToUnicode.hs`
  - `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Types.hs`

Relevant upstream writer facts:

- `makeStretchy` adds `stretchy="true"` and a MathML `form` attribute for
  prefix, infix, and postfix delimiter positions.
- `makeFence` adds `stretchy="false"` and `form` for non-stretchy open/close
  symbols.
- `makeArray` writes alignment on each `mtd`, not just on the `mtable`, and
  also writes `style="text-align: ..."`; right-left alignment sequences add
  zero side padding for tighter equation layout.
- Styled text output uses `toUnicode` for MathML token text, not only the
  `mathvariant` attribute, because renderer support for `mathvariant` remains
  uneven.

## Current Coverage Matrix

| Area | Current PHP coverage | Upstream writer fidelity delta | Classification |
| --- | --- | --- | --- |
| Math root/display wrapper | `MathTexConverter` emits `<math xmlns="http://www.w3.org/1998/Math/MathML" display="block|inline">` and preserves source TeX in a `semantics` annotation. | Source annotation is a local review extension; display/xmlns are aligned. | Covered; local annotation is intentional. |
| Stretchy delimiter positions | `\left(a\middle\|b\right)` parses and renders as fenced/stretchy operators, with the middle delimiter marked `separator="true"`. | Upstream also emits `form="prefix"`, `form="infix"`, and `form="postfix"` on delimiter operators. PHP currently emits no `form` attributes. | Writer-only fidelity gap. Parser parity is present for this fixture. |
| Plain open/close symbol form | Local ordinary open/close tokens do not expose upstream-style `form` metadata. | Upstream `makeFence` adds `form` and `stretchy="false"` for `ESymbol Open` and `ESymbol Close`. | Writer-only fidelity gap; lower priority than `\left...\middle...\right`. |
| Array column alignment | `array`, matrix, AMS row environments, `alignedat`, `flalign`, `multline`, `subarray`, repeated preambles, width columns, line metadata, hooks, and `\multicolumn` are covered with `mtable columnalign` and selected `mtd` metadata for multicolumn cells. | Upstream `makeArray` writes `columnalign` on each `mtd`; PHP normally writes it once on `mtable`. | Writer fidelity gap. Parser parity is mostly present for core `l/c/r` arrays. |
| Per-cell `style="text-align"` | PHP MathML currently does not write per-cell `style` text alignment for arrays. | Upstream writes `style="text-align: left|right|center"` on every aligned cell. | Writer/layout fidelity gap. This is an accepted semantic-layout difference if consumers honor `mtable columnalign`, but it matters for browser/HTML parity. |
| Right-left alignment padding | No local equivalent of upstream right-left sequence padding metadata was found. | Upstream adds `padding-left: 0` or `padding-right: 0` when `isRLSequence` applies. | Low-priority layout gap; useful only after per-cell style is implemented. |
| Multicolumn cells | PHP has bounded `\multicolumn` support with `columnspan`, cell `columnalign`, line provenance, width/vertical-align metadata, and hook metadata. | Upstream `Exp` array representation is simpler; this local metadata is review-oriented and not a direct upstream writer-byte target. | Covered as local extension; not a blocker for this audit. |
| Styled Latin/digit Unicode | PHP rewrites ASCII letters and digits for bold, italic, normal, sans-serif, monospace, double-struck, script, fraktur, bold italic, bold sans-serif, bold script, bold fraktur, sans-serif italic, and sans-serif bold italic variants. | Matches the important current TexMath `toUnicode` behavior for the covered Latin/digit families. | Covered. |
| Styled Greek Unicode | PHP covers bold, italic, bold-italic, bold-sans-serif, and sans-serif-bold-italic Greek identifiers and accessibility reverse mapping. | Upstream `ToUnicode` also contains styled symbol entries beyond the currently covered Greek alphabet rows. | Partially covered. Remaining gaps split between writer rewrite and parser command tables. |
| Double-struck Greek/symbol Unicode | Probe `\mathbb{\pi\gamma\Gamma\Pi\sum}` currently leaves base glyphs inside a double-struck `mstyle`. | Upstream `ToUnicode` maps double-struck pi/gamma/Gamma/Pi/sum to dedicated Unicode codepoints. | Writer Unicode gap. |
| Styled operator/symbol Unicode | Probe `\mathbfit{\nabla\partial...}` leaves `nabla` and `partial` as base operators inside `mstyle`. | Upstream `ToUnicode` has styled math symbol rows for several non-letter symbols. | Writer Unicode gap once the parser emits known symbols. |
| `\varkappa` / `\varpi` | Probe `\varkappa + \varpi` renders literal identifiers `<mi>\varkappa</mi>` and `<mi>\varpi</mi>`. | These must be recognized before writer-level styled Unicode conversion can apply. | Parser command-table blocker, not a MathML writer-only gap. |

## Prioritized Fixtures And Gaps

### P1: Add form attributes to delimiter MathML

Fixture:

```tex
\left(a\middle|b\right)
```

Expected direction:

- Opening delimiter gets `form="prefix"`.
- Middle delimiter gets `form="infix"`.
- Closing delimiter gets `form="postfix"`.
- Existing local `fence`, `stretchy`, `separator`, and source annotation
  behavior should remain stable unless a separate compatibility decision says
  otherwise.

Why P1: this is a clean writer-only delta. The parser already distinguishes
left, middle, and right delimiters.

### P1: Add per-cell array alignment metadata

Fixture:

```tex
\begin{array}{lcr}a&b&c\\x&y&z\end{array}
```

Expected direction:

- Each generated `mtd` should carry the effective cell alignment, matching
  upstream writer placement.
- Table-level `columnalign="left center right"` can remain as a local
  compatibility aid, but upstream fidelity requires cell-level attributes too.

Why P1: the parser and column-spec support are already mature. The remaining
gap is where the writer places alignment metadata.

### P1: Add per-cell `style="text-align: ..."` metadata

Use the same `lcr` fixture as above. Expected direction:

- Left cells: `style="text-align: left"`.
- Center cells: `style="text-align: center"`.
- Right cells: `style="text-align: right"`.

Why P1: upstream explicitly carries CSS text alignment for renderer parity.
Without it, downstream HTML/browser review surfaces can diverge even when
semantic `columnalign` is present.

### P2: Add right-left sequence padding style

Fixture candidate:

```tex
\begin{array}{rl}a&=b\\x&=y\end{array}
```

Expected direction:

- Match upstream `isRLSequence` handling by adding side-specific zero padding
  in the per-cell style once per-cell style exists.

Why P2: this is layout fidelity, but it should wait until the P1 per-cell
style path exists.

### P2: Complete styled Unicode rewrite for double-struck Greek/symbols

Fixture:

```tex
\mathbb{\pi\gamma\Gamma\Pi\sum}
```

Expected direction:

- Rewrite the covered double-struck Greek/symbol codepoints that upstream
  `ToUnicode` maps, rather than leaving base pi/gamma/Gamma/Pi/sum glyphs
  under a `mathvariant="double-struck"` wrapper.

Why P2: styled Latin/digit and several Greek variant families are already
covered. This is a bounded extension of the existing writer rewrite table.

### P2: Complete styled Unicode rewrite for parsed symbol tokens

Fixture:

```tex
\mathbfit{\nabla\partial\epsilon\vartheta\phi\varrho}
```

Expected direction:

- Rewrite styled symbols supported by upstream `ToUnicode`, including symbols
  that local parsing already maps to operator or identifier glyphs.

Why P2: local rewriting currently targets `mi` and `mn` text. Upstream applies
styled Unicode through the token writer path more broadly.

### P3: Add parser aliases before styled writer tests for missing commands

Fixture:

```tex
\varkappa + \varpi
```

Expected direction:

- First map these commands to the correct base Unicode glyphs.
- Only then add styled-variant coverage such as
  `\mathbfit{\varkappa\varpi}`.

Why P3: this is not a writer-only gap. It should be scheduled under parser
command-table parity before writer fidelity tests depend on it.

## Accepted Differences

- Local `semantics` annotations that preserve source TeX are useful review
  metadata and should not be treated as upstream writer drift.
- Local `data-tex-*` attributes for row labels, environment positions,
  multicolumn provenance, array hooks, and rule metadata are bounded review
  extensions. They are not blockers for TexMath MathML writer fidelity unless a
  downstream consumer requires byte-for-byte upstream MathML.
- Table-level `mtable columnalign` is acceptable semantic MathML. The fidelity
  gap is only that upstream also repeats alignment and CSS text alignment at
  the cell level.

## Verification

Commands run from `port_libs/polecats/moonstone/port_libs`:

```text
git ls-remote https://github.com/jgm/texmath.git HEAD
170899673ee31de9096e178605e8da31a36e4185	HEAD
```

```text
git ls-remote https://github.com/jgm/pandoc.git HEAD
c568a0e66599384fdabc08ad656fd5beeac39ae1	HEAD
```

```text
curl -L https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Writers/MathML.hs
curl -L https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Unicode/ToUnicode.hs
curl -L https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Types.hs
```

Local probes:

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach (["\\left(a\\middle|b\\right)", "\\begin{array}{lcr}a&b&c\\\\x&y&z\\end{array}", "\\begin{array}{lcr}a&\\multicolumn{2}{|r|}{bc}\\\\x&y&z\\end{array}", "\\mathbb{AZ09} + \\mathcal{FLO} + \\mathbfit{\\Gamma\\alpha}"] as $tex) { echo "--- ".$tex."\n"; echo $c->texToMathMl($tex, true)."\n"; }'
```

Observed:

- `\left...\middle...\right` has local fence/stretch/separator metadata, but
  no `form` attributes.
- `array{lcr}` has table-level `columnalign`, but no per-cell `columnalign`
  or `style`.
- `\multicolumn` already has cell-level span/alignment/provenance metadata.
- Covered Latin/digit and covered Greek variant output rewrites to Unicode
  mathematical alphanumeric glyphs.

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach (["\\mathbb{\\pi\\gamma\\Gamma\\Pi\\sum}", "\\mathbfit{\\nabla\\partial\\epsilon\\vartheta\\varkappa\\phi\\varrho\\varpi}", "\\mathbfsfup{\\nabla\\partial}", "\\mathsfit{\\Gamma\\alpha}"] as $tex) { echo "--- ".$tex."\n"; echo $c->texToMathMl($tex, true)."\n"; }'
```

Observed:

- Double-struck Greek/symbols remain base glyphs inside `mstyle`.
- `nabla` and `partial` remain base operators inside styled wrappers.
- `\varkappa` and `\varpi` remain literal command identifiers and need parser
  alias work before writer fidelity can be measured.

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach (["\\varkappa + \\varpi", "\\nabla + \\partial + \\epsilon + \\vartheta + \\phi + \\varrho"] as $tex) { echo "--- ".$tex."\n"; echo $c->texToMathMl($tex, true)."\n"; }'
```

Focused gate context:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1345 assertions, 6 failures
```

The six failures are existing current-branch failures in macro capture /
paired-delimiter and LaTeX writer source-handoff cases. This audit did not
change runtime code.

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
```

Result: failed in the same paired-delimiter macro-definition path before any
audit file edits.

# EPUB3 Supervisor Decision: EPUB2 Output Scope

Date: 2026-06-25
Issue: `plib-5um9x.8`

## Decision

No additional EPUB2 output parity is in scope for the EPUB3 closure lane beyond
a bounded OPF 2.0/NCX compatibility writer mode.

For this lane, EPUB2 output is not a parallel full-format parity target. The
only EPUB2 output behavior that may count toward EPUB3 closure is compatibility
behavior needed to prevent EPUB3 package work from reopening on legacy OPF 2.0
and NCX edges, such as OPF 2.0 package shape, NCX navigation, guide references,
linear spine handling, and suppression of EPUB3-only package/spine metadata
when an explicit bounded EPUB2 mode exists.

Anything deeper remains out of scope for this EPUB3 closure:

- full upstream Pandoc EPUB2 writer parity;
- reading-system compatibility matrices;
- EPUBCheck-backed EPUB2 validation;
- arbitrary EPUB2 fixture corpus parity;
- legacy renderer or device-specific behavior;
- broad XHTML/CSS/layout fidelity that is not required by the bounded
  OPF 2.0/NCX compatibility surface;
- claiming `epub2` as a direct supported output format while the registry still
  marks it unsupported.

## Current Mainline Evidence

Current `origin/main` keeps the EPUB2 boundary explicit:

- `PandocFormatRegistry` tracks `epub2` in the upstream output denominator, but
  reports `epub2` output support as `unsupported`.
- `RichPackageUnsupportedFormatRegistry` reports `epub2` output as
  `unsupported-rich-package-output`, with no writer component and diagnostics
  including `writer-component-missing` and `package-assembly-not-implemented`.
- `EpubWriter` and `EpubWriterTest` cover bounded native EPUB/EPUB3 output
  through `epub` and `epub3`; they do not claim direct `epub2` output.

## Closure Rule

Do not open new EPUB2 output implementation work from the EPUB3 closure lane
unless the requested slice is narrowly necessary to keep bounded OPF 2.0/NCX
compatibility stable. Otherwise, record the gap as out of scope for EPUB3 and
leave broader EPUB2 output parity to a separate, explicit EPUB2 writer project.

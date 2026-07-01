# Pandoc BibLaTeX Original Source Bibliography Slice - 2026-07-01

Slice: `plib-p8ei9`

`BibtexCslProcessor::renderBibliographyText()` now surfaces singular legacy BibLaTeX original source metadata in direct bibliography output:

- `original-title` and `original-title-addon`
- `original-date`
- `original-publisher` and `original-publisher-place`
- `original-language`

The direct bibliography path avoids duplicating the existing multi-value publisher/place/language list summaries when those list fields are present. The same fixture verifies the parsed CSL item, `CitationCslProcessor` variable aliases, citation handoff, and WordPress bibliography output without invoking external Pandoc, citeproc, BibTeX, Biber, validators, or package tools.

Focused mapped accounting: +1 case, +18 assertions; denominator `mapped` is now 2,317 on this integration branch.

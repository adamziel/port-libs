# BibLaTeX related type labels

Slice `plib-ujt3h` keeps the CSL relation handoff metadata-only while making unlabelled common BibLaTeX relation types render with semantic fallback labels.

- Known `relatedtype` values such as `reprintof`, `translationof`, `reviewof`, and `updated-by` now produce labels like `Reprint of`, `Translation of`, `Review of`, and `Updated by` when `relatedstring` is absent.
- Hyphenated and underscored aliases normalize through the same path.
- Unknown custom relation types still render as `Related source (type)` so raw relation metadata remains visible.

No external citeproc, BibTeX, Biber, Pandoc, office, TeX/browser, Typst, Node, zip/unzip, validators, or live services are required for this slice.

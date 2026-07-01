# CSL JSON Relation Review Rollups

`BibliographyReader('csljson')` now carries metadata-only relation-field rollups for direct CSL JSON bibliography inputs:

- bibliography-level relation-bearing item counts;
- relation field occurrence counts;
- relation reference candidate counts for scalar, list, and object-list relation fields;
- per-item relation reference counts alongside existing relation field names.

The review payload continues to omit source values and does not invoke citeproc, BibTeX, Biber, Pandoc, or external validators.

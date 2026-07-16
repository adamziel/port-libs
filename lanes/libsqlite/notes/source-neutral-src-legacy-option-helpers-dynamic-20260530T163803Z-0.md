# Source-neutral dynamic option helper cleanup

Slice: `source-neutral-src-legacy-option-helpers-dynamic-20260530T163803Z-0`

This cleanup owns the dynamic recursive-view RETURNING compatibility helpers in
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`.

Changed source internals:

- `signatureOptionCurrentSourceResumeFence()` is now
  `signatureValueCurrentSourceResumeFence()`.
- `withCurrentReturningSourceSealOptionAliases()` is now
  `withCurrentReturningSourceSealLegacyInputAliases()`.
- `withCurrentReturningGenerationOptionAliases()` is now
  `withCurrentReturningGenerationLegacyInputAliases()`.
- `withCurrentSourceTicketOptionAliases()` is now
  `withCurrentSourceTicketLegacyInputAliases()`.

The observable legacy input keys and result compatibility aliases are preserved
so existing generated tests keep the same behavior. This patch does not claim
new upstream denominator rows or PHP PASS-line growth.

Dependency closure: no new support component is needed; the existing recursive
view RETURNING implementation and no-domain API guard are reused.

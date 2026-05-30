Source-neutral CAST/LIKE/GLOB defaults dynamic cleanup

- Slice: source-neutral-src-cast-like-glob-defaults-dynamic-20260530T192536Z-0
- Owned source: SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationBinaryCollationDefaultLikePlan() and its private nextTwoFiveNine helpers.
- Production neutralization: changed the default BINARY LIKE row contract from option_id/option_name/option_name_bytes/option_value to setting_id/key_name/key_name_bytes/key_value, and updated diagnostics plus expression/non-overlap text to use generic setting-key terminology.
- Direct tests/examples: updated SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Test.php and application-encoding-binary-like-current-source-next259.php to use the generic row shape while preserving the same LIKE behavior assertions.
- Dependency closure: no new support component needed; this reuses native LIKE matching, BINARY collation byte keys, mixed UTF decoding, scalar text-affinity, and current-source diagnostics.
- Guard status: SQLiteNoWordPressSpecificApiTest.php is not present in this worktree, so the required no-domain guard could not be run.

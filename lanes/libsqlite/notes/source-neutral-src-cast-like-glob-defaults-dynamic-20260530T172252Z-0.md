Source-neutral cleanup for the encoding/cast LIKE/GLOB source slice.

- Base accepted HEAD: 99dfad49eb8b3659a920d2be780c5f32d787d8ac.
- Production files neutralized: SQLiteEncodingCollationSourceCursor, SQLiteEncodingLikeGlobSourceSwitchPlan, SQLiteLikeGlobCurrentSourceNextPlan, SQLiteMalformedLikeGlobSourceNextPlan, SQLiteNocaseLikeRtrimCurrentSourceNextPlan, SQLiteRtrimNocaseGlobCurrentSourceNextPlan, SQLiteUtf16NocaseLikeCurrentSourceNextPlan, and SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan.
- Row inputs and diagnostics now use generic setting/key/load-policy terms: setting_id, key_name, key_name_bytes, and load_policy.
- Direct tests and examples were updated to keep the same LIKE/GLOB, UTF-16, malformed-text, RTRIM, NOCASE, source-switch, and cursor invalidation assertions.
- The existing source-neutral defaults guard now covers this owned source group.
- Dependency closure: no new support component is needed; the cleanup reuses the existing PHP UTF text decoder, LIKE/GLOB range planners, collation cursor, and current-source invalidation helpers.

# real-upstream-corpus-trigger-fkey-dynamic-quoted-cascade-20260531

Base accepted HEAD: `5237a0589958b13a7df177706c832014179deb3d`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test`.

Ported behavior cluster:
- `fkey1-4.0` and `fkey1-4.1`: quoted and doubled-quote parent/child identifiers are dequoted once before FK cascade lookup.
- `fkey1-5.2`: `INSERT OR REPLACE` / implicit delete interactions preserve FK failure behavior.
- Partial-parent-index mismatch remains modeled as `foreign-key-mismatch`.

Implementation delta:
- `SQLiteDynamicTriggerForeignKeyPlan::quotedIdentifier()` now normalizes SQLite double-quoted, bracket-quoted, and backtick-quoted identifiers before row lookup while preserving existing bare-token fixture behavior.

Focused assertion delta:
- New focused file adds `5291` passing assertions.
- Adjacent fkey1 family check passed with `15181` assertions across the existing fkey1 corpus plus this new file.

Dependency closure:
- No new support component needed. The slice reuses the existing dynamic trigger/FK plan and hydrated upstream SQLite test cache.

Root harness:
- Not run - isolated micro-slice.

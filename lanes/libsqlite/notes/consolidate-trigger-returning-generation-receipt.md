# Trigger RETURNING Generation Receipt Consolidation

This cleanup pass consolidates one remaining numbered production method/helper family in
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`.

- Renamed `executeNext203()` to `executeCurrentGenerationReceipt()`.
- Renamed the private `*Next203` helpers used by that entrypoint to descriptive
  current-generation receipt names.
- Updated the direct focused test and WordPress smoke caller.
- Kept existing result keys, status strings, dependency labels, and asserted
  payloads stable so this is a naming consolidation only.

No new support component is needed; this reuses the existing native recursive
view RETURNING current-source generation handoff implementation.

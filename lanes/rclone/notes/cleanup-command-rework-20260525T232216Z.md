# OneDrive Cleanup Command Rework Closure - 2026-05-25 23:22 UTC

## Scope

This isolated lane patch refreshes the stale OneDrive cleanup-command rework on top of the accepted rclone lane baseline. It keeps prior cleanup command and RC evidence intact and adds one bounded command-mode acceptance edge that does not require live OneDrive credentials.

## Additive Behavior

- A valid single command remote such as `onedrive:exports` is accepted before the disabled no-versions short-circuit, so command arity no longer masks the intended no-provider/no-traversal bypass.
- The same valid single command remote reaches the existing cleanup feature gate when version cleanup is enabled, still stopping before traversal and provider/version work when cleanup is feature-masked.
- Missing, extra, and empty command remotes remain rejected before feature checks, traversal, provider calls, and disabled no-versions bypasses.
- RC `operations/cleanup` remains `fs`-parameter driven and continues to ignore command-style `remoteArgs` after `fs` is present.

## Dependency Closure

No new support component is needed. This reuses the lane-local bounded OneDrive cleanup/version-cleaner simulation and avoids OAuth, provider config, token stores, cloud remotes, and live provider integration tests.

## Verification Plan

Focused verification should include PHP syntax checks for changed PHP files, `OneDriveCleanupCommandTest.php`, the local WordPress cleanup example smoke, lane JSON validation, and `git diff --check -- lanes/rclone`.

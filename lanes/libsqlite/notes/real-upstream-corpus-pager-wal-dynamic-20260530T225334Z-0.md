# real-upstream-corpus-pager-wal-dynamic-20260530T225334Z-0

Added a focused real upstream pager/WAL dynamic matrix for `walvfs.test`
sections 4.1, 4.2, 5.3, 5.4, 5.5, 5.6, 6.2, 7.1, 8.3, and 9.1.

The new test file exercises `SQLiteWalVfsDynamicPlan::shmBoundary()` across
1,000 distinct focused TestRunner cases, varying busy retry counts, WAL frame
counts, and checkpoint backfill limits while preserving upstream-observed WAL
VFS SHM/readmark outcomes:

- readonly `xShmMap` failures from walvfs-4 and walvfs-5
- successful readmark reclamation from walvfs-5
- protocol/busy checkpoint lock boundaries from walvfs-6 and walvfs-7
- checkpoint cache refresh from walvfs-8
- readonly/IOERR precedence from walvfs-9

No new support component is needed. This reuses the existing native
`SQLiteWalVfsDynamicPlan` model and existing TestRunner autoloading. The batch
does not change `lane-status.json`; the integrator can count it as focused
PASS-line growth after accepting the patch.

<?php

declare(strict_types=1);

use PortLibs\Gitoxide\IndexEntry;

$time = static fn (int $secs = 0, int $nsecs = 0): array => IndexEntry::time($secs, $nsecs);
$stat = static fn (array $values = []): array => IndexEntry::stat($values);

return [
    'upstream entry flags from_stage' => static function (TestRunner $t): void {
        foreach ([
            IndexEntry::STAGE_NORMAL,
            IndexEntry::STAGE_ANCESTOR,
            IndexEntry::STAGE_OURS,
            IndexEntry::STAGE_THEIRS,
        ] as $stage) {
            $actual = IndexEntry::flagsFromStage($stage);
            $t->same($stage, IndexEntry::stageFromFlags($actual));
            $t->same($stage, IndexEntry::stageFromFlags(IndexEntry::flagsFromStage($stage)));
        }
    },
    'upstream mode apply' => static function (TestRunner $t): void {
        $t->same(
            IndexEntry::MODE_FILE_EXECUTABLE,
            IndexEntry::applyModeChange(IndexEntry::MODE_CHANGE_EXECUTABLE_BIT, IndexEntry::MODE_FILE),
        );
        $t->same(
            IndexEntry::MODE_FILE,
            IndexEntry::applyModeChange(IndexEntry::MODE_CHANGE_EXECUTABLE_BIT, IndexEntry::MODE_FILE_EXECUTABLE),
        );
        $t->same(
            IndexEntry::MODE_SYMLINK,
            IndexEntry::applyModeChange(IndexEntry::MODE_CHANGE_TYPE, IndexEntry::MODE_FILE, IndexEntry::MODE_SYMLINK),
        );
    },
    'upstream mode debug' => static function (TestRunner $t): void {
        $t->same(
            'Mode(FILE)',
            IndexEntry::modeDebug(IndexEntry::MODE_FILE),
            'Assure the debug output is easy to understand',
        );
        $t->same(
            'Some(Mode(FILE | SYMLINK | 0x40))',
            IndexEntry::modeDebugFromBits(0o120744),
            'strange modes are also mostly legible',
        );
    },
    'upstream time conversion_roundtrip' => static function (TestRunner $t) use ($time): void {
        foreach ([
            $time(),
            $time(42, 100),
        ] as $sample) {
            $other = IndexEntry::timeToUnixNanoseconds($sample);
            $newSample = IndexEntry::timeFromUnixNanoseconds($other);
            $t->same(
                $sample,
                $newSample,
                'sample is still the same after conversion to system-time and back',
            );
        }
    },
    'upstream stat matches use_nsec' => static function (TestRunner $t) use ($time, $stat): void {
        $stat1 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(0, 0),
        ]);
        $stat2 = $stat([
            'mtime' => $time(0, 10),
            'ctime' => $time(0, 0),
        ]);

        $t->same(true, IndexEntry::statMatches($stat1, $stat2), "nsec differences don't matter without use_nsec");
        $t->same(false, IndexEntry::statMatches($stat1, $stat2, ['use_nsec' => true]), 'use_nsec works');
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['use_nsec' => true, 'check_stat' => false]),
            "nsec differences don't matter without check_stat",
        );
    },
    'upstream stat matches use_ctime' => static function (TestRunner $t) use ($time, $stat): void {
        $stat1 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(1, 2),
        ]);
        $stat2 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(3, 4),
        ]);

        $t->same(
            false,
            IndexEntry::statMatches($stat1, $stat2),
            "ctime is different so stat doesn't match (trust_ctime=true)",
        );
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['trust_ctime' => false]),
            'stat matches even tough ctime is different (trust_ctime=false)',
        );
        $stat2['ctime']['secs'] = 1;
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2),
            'ctime seconds are the same so stat matches (trust_ctime=true,use_nsec=false)',
        );
        $t->same(
            false,
            IndexEntry::statMatches($stat1, $stat2, ['use_nsec' => true]),
            "ctime nsecs are different so stat doesn't match (trust_ctime=true,use_nsec=true)",
        );
    },
    'upstream stat matches use_stdev' => static function (TestRunner $t) use ($time, $stat): void {
        $stat1 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(0, 0),
        ]);
        $stat2 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(0, 0),
            'dev' => 1,
        ]);

        $t->same(true, IndexEntry::statMatches($stat1, $stat2), 'differences in dev number are ignored');
        $t->same(
            false,
            IndexEntry::statMatches($stat1, $stat2, ['use_stdev' => true]),
            'differences in dev number change comparison result if use_stdev=true',
        );
    },
    'upstream stat matches check_stat' => static function (TestRunner $t) use ($time, $stat): void {
        $stat1 = $stat([
            'mtime' => $time(0, 0),
            'ctime' => $time(0, 0),
        ]);
        $stat2 = $stat1;

        $t->same(true, IndexEntry::statMatches($stat1, $stat2), 'identical stats always match');
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['check_stat' => false]),
            'identical stats always match',
        );

        $stat2 = $stat1;
        $stat2['ino'] = 1;
        $t->same(false, IndexEntry::statMatches($stat1, $stat2), 'inode is different => mismatch (check_stat=true)');
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['check_stat' => false]),
            "inode difference doesnt' matter (check_stat=false)",
        );

        $stat2 = $stat1;
        $stat2['uid'] = 1;
        $t->same(false, IndexEntry::statMatches($stat1, $stat2), 'uid is different => mismatch (check_stat=true)');
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['check_stat' => false]),
            "uid difference doesnt' matter (check_stat=false)",
        );

        $stat2 = $stat1;
        $stat2['gid'] = 1;
        $t->same(false, IndexEntry::statMatches($stat1, $stat2), 'gid is different => mismatch (check_stat=true)');
        $t->same(
            true,
            IndexEntry::statMatches($stat1, $stat2, ['check_stat' => false]),
            "gid difference doesnt' matter (check_stat=false)",
        );

        $stat2 = $stat1;
        $stat2['size'] = 1;
        $t->same(false, IndexEntry::statMatches($stat1, $stat2), 'size is different => mismatch (check_stat=true)');
        $t->same(
            false,
            IndexEntry::statMatches($stat1, $stat2, ['check_stat' => false]),
            'size is different => mismatch (check_stat=false)',
        );
    },
    'upstream stat is_racy' => static function (TestRunner $t) use ($time, $stat): void {
        $stat1 = $stat([
            'mtime' => $time(1, 10),
            'ctime' => $time(0, 0),
        ]);

        $t->same(
            true,
            IndexEntry::statIsRacy($stat1, $time(1, 0)),
            'entry with mtime identical (seconds) to timestamp is racy (use_nsec=false)',
        );
        $t->same(
            true,
            IndexEntry::statIsRacy($stat1, $time(1, 0), ['use_nsec' => true]),
            'entry with mtime after timestamp (nanoseconds) is racy (use_nsec=true)',
        );
        $t->same(
            true,
            IndexEntry::statIsRacy($stat1, $time(1, 10)),
            'entry with mtime identical (seconds) to timestamp is racy (use_nsec=false)',
        );
        $t->same(
            true,
            IndexEntry::statIsRacy($stat1, $time(1, 10), ['use_nsec' => true]),
            'entry with mtime identical (seconds and nanseconds) to timestamp is racy (use_nsec=true)',
        );
        $t->same(
            true,
            IndexEntry::statIsRacy($stat1, $time(1, 20)),
            'entry with mtime identical (seconds) to timestamp is racy (use_nsec=false)',
        );
        $t->same(
            false,
            IndexEntry::statIsRacy($stat1, $time(1, 20), ['use_nsec' => true]),
            'entry with mtime before (nanoseconds) timestamp is not racy (use_nsec=true)',
        );
        $t->same(
            false,
            IndexEntry::statIsRacy($stat1, $time(2, 0)),
            'entry with mtime before (seconds) timestamp is not racy (use_nsec=false)',
        );
        $t->same(
            false,
            IndexEntry::statIsRacy($stat1, $time(2, 0), ['use_nsec' => true]),
            'entry with mtime before (seconds) timestamp is not racy (use_nsec=true)',
        );
    },
    'upstream main size_of_entry' => static function (TestRunner $t): void {
        $sha1 = IndexEntry::UPSTREAM_ENTRY_SIZE_SHA1_BYTES;
        $sha256Extra = IndexEntry::UPSTREAM_ENTRY_SIZE_SHA256_EXTRA_BYTES;
        $expected = $sha1 + $sha256Extra;

        $t->same(true, IndexEntry::sizeOk(IndexEntry::UPSTREAM_ENTRY_SIZE_BYTES, $expected));
    },
    'upstream main size_of_entry_time' => static function (TestRunner $t): void {
        $t->same(true, IndexEntry::sizeOk(IndexEntry::UPSTREAM_TIME_SIZE_BYTES, 8));
        $t->same(true, IndexEntry::sizeOk(IndexEntry::UPSTREAM_FILETIME_SIZE_BYTES, 16));
    },
];

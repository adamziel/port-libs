<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitDate;
use PortLibs\Gitoxide\GitDateFormat;

$time = static fn (): GitDate => new GitDate(123456789, 9000);
$timeDec1 = static fn (): GitDate => new GitDate(123543189, 9000);
$assertTime = static function (TestRunner $t, int $seconds, int $offset, GitDate $actual, string $message): void {
    $t->same($seconds, $actual->seconds, "{$message} seconds");
    $t->same($offset, $actual->offset, "{$message} offset");
};

return [
    'upstream format short' => static function (TestRunner $t) use ($time): void {
        $t->same('1973-11-30', $time()->format(GitDateFormat::SHORT));
    },
    'upstream format unix' => static function (TestRunner $t) use ($time): void {
        $expected = '123456789';
        $t->same($expected, $time()->format(GitDateFormat::UNIX));
    },
    'upstream format raw' => static function (TestRunner $t) use ($time): void {
        foreach ([
            [$time(), '123456789 +0230'],
            [new GitDate(1112911993, 3600), '1112911993 +0100'],
        ] as [$date, $expected]) {
            $t->same($expected, $date->format(GitDateFormat::RAW));
        }
    },
    'upstream format iso8601' => static function (TestRunner $t) use ($time): void {
        $t->same('1973-11-30 00:03:09 +0230', $time()->format(GitDateFormat::ISO8601));
    },
    'upstream format iso8601 strict' => static function (TestRunner $t) use ($time): void {
        $t->same('1973-11-30T00:03:09+02:30', $time()->format(GitDateFormat::ISO8601_STRICT));
    },
    'upstream format rfc2822' => static function (TestRunner $t) use ($time, $timeDec1): void {
        $t->same('Fri, 30 Nov 1973 00:03:09 +0230', $time()->format(GitDateFormat::RFC2822));
        $t->same('Sat, 01 Dec 1973 00:03:09 +0230', $timeDec1()->format(GitDateFormat::RFC2822));
    },
    'upstream format git rfc2822' => static function (TestRunner $t) use ($time, $timeDec1): void {
        $t->same('Fri, 30 Nov 1973 00:03:09 +0230', $time()->format(GitDateFormat::GIT_RFC2822));
        $t->same('Sat, 1 Dec 1973 00:03:09 +0230', $timeDec1()->format(GitDateFormat::GIT_RFC2822));
    },
    'upstream format default gitoxide' => static function (TestRunner $t) use ($time, $timeDec1): void {
        $t->same('Fri Nov 30 1973 00:03:09 +0230', $time()->format(GitDateFormat::GITOXIDE));
        $t->same('Sat Dec 01 1973 00:03:09 +0230', $timeDec1()->format(GitDateFormat::GITOXIDE));
    },
    'upstream format or unix' => static function (TestRunner $t): void {
        $t->same('42', (new GitDate(42, 7200 * 60))->formatOrUnix(GitDateFormat::GITOXIDE));
    },
    'upstream format git default' => static function (TestRunner $t) use ($time, $timeDec1): void {
        $t->same('Fri Nov 30 00:03:09 1973 +0230', $time()->format(GitDateFormat::DEFAULT));
        $t->same('Sat Dec 1 00:03:09 1973 +0230', $timeDec1()->format(GitDateFormat::DEFAULT));
    },
    'upstream compact iso8601 full format' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203021045, 0, GitDate::parse('20080214T203045'), '20080214T203045');
    },
    'upstream compact iso8601 with colons in time' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203021045, 0, GitDate::parse('20080214T20:30:45'), '20080214T20:30:45');
    },
    'upstream compact iso8601 hour minute only' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203021000, 0, GitDate::parse('20080214T2030'), '20080214T2030');
    },
    'upstream compact iso8601 hour minute with colon' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203021000, 0, GitDate::parse('20080214T20:30'), '20080214T20:30');
    },
    'upstream compact iso8601 hour only' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203019200, 0, GitDate::parse('20080214T20'), '20080214T20');
    },
    'upstream compact iso8601 with timezone' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203035445, -14400, GitDate::parse('20080214T203045-04:00'), '20080214T203045-04:00');
    },
    'upstream compact iso8601 with space before timezone' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203035445, -14400, GitDate::parse('20080214T203045 -04:00'), '20080214T203045 -04:00');
    },
    'upstream compact iso8601 with subseconds ignored' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203035445, -14400, GitDate::parse('20080214T203045.019-04:00'), '20080214T203045.019-04:00');
    },
    'upstream compact iso8601 with subseconds no timezone' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1202947200, 0, GitDate::parse('20080214T000000.20'), '20080214T000000.20');
    },
    'upstream compact iso8601 with subseconds colon time' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1202947200, 0, GitDate::parse('20080214T00:00:00.20'), '20080214T00:00:00.20');
    },
    'upstream flexible offset z suffix for utc' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 0, 0, GitDate::parse('1970-01-01 00:00:00 Z'), '1970-01-01 00:00:00 Z');
    },
    'upstream flexible offset two digit hour offset' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203039045, -18000, GitDate::parse('2008-02-14 20:30:45 -05'), '2008-02-14 20:30:45 -05');
    },
    'upstream flexible offset colon separated offset' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203039045, -18000, GitDate::parse('2008-02-14 20:30:45 -05:00'), '2008-02-14 20:30:45 -05:00');
    },
    'upstream flexible offset fifteen minute offset' => static function (TestRunner $t) use ($assertTime): void {
        $assertTime($t, 1203021945, -900, GitDate::parse('2008-02-14 20:30:45 -0015'), '2008-02-14 20:30:45 -0015');
    },
];

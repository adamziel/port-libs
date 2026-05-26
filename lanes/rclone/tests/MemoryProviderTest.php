<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'memory provider stores object metadata and copies content' => static function (TestRunner $t): void {
        $a = new MemoryProvider();
        $b = new MemoryProvider();
        $info = $a->put('/site/export.wxr', 'content');
        $a->copyTo('site/export.wxr', $b, 'backup/export.wxr');
        $t->same(7, $info->size);
        $t->same('content', $b->get('backup/export.wxr'));
    },
    'sync plan reports missing and checksum changed paths' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('a.txt', 'one');
        $source->put('b.txt', 'two');
        $target->put('a.txt', 'changed');
        $t->same(['a.txt', 'b.txt'], (new SyncPlan())->changedPaths($source, $target));
    },
];


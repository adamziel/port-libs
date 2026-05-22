<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\MultiHasher;
use PortLibs\Rclone\SyncPlan;

return [
    'maps upstream hash set operations and aliases' => static function (TestRunner $t): void {
        $hashes = new HashSet();
        $t->same(0, $hashes->count());
        $t->same([], $hashes->toArray());

        $hashes->add(HashType::MD5);
        $t->same(1, $hashes->count());
        $t->same(HashType::MD5, $hashes->getOne());
        $t->same([HashType::MD5], $hashes->toArray());
        $t->true($hashes->overlap(HashSet::supported())->contains(HashType::MD5));
        $t->true($hashes->subsetOf(HashSet::supported()));
        $t->true($hashes->subsetOf(new HashSet(HashType::MD5)));

        $hashes->add(HashType::SHA1);
        $t->same(2, $hashes->count());
        $t->same(HashType::MD5, $hashes->getOne());
        $t->true(!$hashes->subsetOf(new HashSet(HashType::MD5)));
        $t->true(!$hashes->subsetOf(new HashSet(HashType::SHA1)));
        $t->true($hashes->subsetOf(new HashSet(HashType::MD5, HashType::SHA1)));
        $t->same('[md5, sha1]', (string) $hashes);

        $overlap = $hashes->overlap(new HashSet(HashType::MD5));
        $t->same(1, $overlap->count());
        $t->true($overlap->contains(HashType::MD5));
        $t->true(!$overlap->contains(HashType::SHA1));

        foreach (['none' => HashType::NONE, 'None' => HashType::NONE, 'md5' => HashType::MD5, 'MD5' => HashType::MD5, 'sha1' => HashType::SHA1, 'SHA-1' => HashType::SHA1, 'SHA1' => HashType::SHA1, 'Sha1' => HashType::SHA1] as $input => $expected) {
            $t->same($expected, HashType::fromString($input));
        }
        $t->throws(InvalidArgumentException::class, static fn () => HashType::fromString('Sha-1'));
    },
    'maps upstream multi hasher fixtures for php supported hashes' => static function (TestRunner $t): void {
        $bytes = '';
        for ($i = 1; $i <= 14; $i++) {
            $bytes .= chr($i);
        }

        $hashes = MultiHasher::hashBytes($bytes, new HashSet(HashType::MD5, HashType::SHA1, HashType::CRC32, HashType::SHA256, HashType::SHA512));
        $t->same('bf13fc19e5151ac57d4252e0e0f87abe', $hashes[HashType::MD5]);
        $t->same('3ab6543c08a75f292a5ecedac87ec41642d12166', $hashes[HashType::SHA1]);
        $t->same('a6041d7e', $hashes[HashType::CRC32]);
        $t->same('c839e57675862af5c21bd0a15413c3ec579e0d5522dab600bc6c3489b05b8f54', $hashes[HashType::SHA256]);
        $t->same('008e7e9b5d94d37bf5e07c955890f730f137a41b8b0db16cb535a9b4cb5632c2bccff31685ec470130fe10e2258a0ab50ab587472258f3132ccf7d7d59fb91db', $hashes[HashType::SHA512]);

        $empty = MultiHasher::hashBytes('', new HashSet(HashType::MD5, HashType::SHA1, HashType::CRC32, HashType::SHA256));
        $t->same('d41d8cd98f00b204e9800998ecf8427e', $empty[HashType::MD5]);
        $t->same('da39a3ee5e6b4b0d3255bfef95601890afd80709', $empty[HashType::SHA1]);
        $t->same('00000000', $empty[HashType::CRC32]);
        $t->same('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $empty[HashType::SHA256]);
    },
    'checks providers using rclone combined report sigils' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('rutabaga', 'is tasty');
        $target->put('rutabaga', 'is tasty');
        $source->put('potato2', str_repeat('-', 60));
        $target->put('empty space', '-');
        $source->put('changed.txt', 'local');
        $target->put('changed.txt', 'remote');

        $result = (new SyncPlan())->check($source, $target);
        $t->same(['rutabaga'], $result->matches);
        $t->same(['changed.txt'], $result->differ);
        $t->same(['empty space'], $result->missingOnSource);
        $t->same(['potato2'], $result->missingOnTarget);
        $t->same(3, $result->differences());
        $t->same(1, $result->matches());
        $t->same(['* changed.txt', '+ potato2', '- empty space', '= rutabaga'], $result->combinedLines());

        $oneWay = (new SyncPlan())->check($source, $target, true);
        $t->same([], $oneWay->missingOnSource);
        $t->same(['* changed.txt', '+ potato2', '= rutabaga'], $oneWay->combinedLines());
    },
    'copies filtered changed wordpress backup objects idempotently' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $copied = (new SyncPlan())->copyChanged($source, $target, $filter);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same([], (new SyncPlan())->changedPaths($source, $target, $filter));
        $t->same($source->get('wp-content/uploads/2026/05/hero.jpg'), $target->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $target->get('wp-content/cache/page-cache.html'));
    },
];

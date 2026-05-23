<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListHelper;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ObjectInfo;

$provider = new MemoryProvider();
$provider->put('database/site.sql', 'insert into wp_posts values (...)');
$provider->put('exports/site.wxr', '<rss version="2.0"></rss>');
$provider->put('exports/site-users.wxr', '<rss version="2.0"><channel>users</channel></rss>');
$provider->put('exports/site-comments.wxr', '<rss version="2.0"><channel>comments</channel></rss>');

for ($i = 1; $i <= 100; $i++) {
    $provider->put(sprintf('wp-content/uploads/2026/05/image-%03d.jpg', $i), 'image bytes ' . $i);
}

$batches = [];
$helper = new ListHelper(static function (array $entries) use (&$batches): void {
    $batches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
});

foreach ($provider->list() as $entry) {
    $helper->add($entry);
}
$helper->flush();

return [
    'batchSizes' => array_map('count', $batches),
    'firstBatchFirstPath' => $batches[0][0],
    'firstBatchLastPath' => $batches[0][99],
    'lastBatchLastPath' => $batches[1][count($batches[1]) - 1],
    'manifestCount' => array_sum(array_map('count', $batches)),
];

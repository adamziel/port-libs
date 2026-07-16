<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderWatchEventAggregator
{
    public const EVENT_NON_REMOVE = 'non-remove';
    public const EVENT_REMOVE = 'remove';
    public const EVENT_MIXED = 'mixed';

    /**
     * @var array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}>
     */
    private array $events = [];

    /**
     * @var array<string, true>
     */
    private array $inProgress = [];

    private ?int $notifyAt = null;
    private readonly int $notifyTimeoutSeconds;

    public function __construct(
        private readonly int $notifyDelaySeconds,
        ?int $notifyTimeoutSeconds = null,
        private readonly int $maxFiles = 512,
        private readonly int $maxFilesPerDir = 128,
    ) {
        if ($this->notifyDelaySeconds < 0) {
            throw new \InvalidArgumentException('Watch notify delay must not be negative');
        }
        if ($this->maxFiles < 1 || $this->maxFilesPerDir < 1) {
            throw new \InvalidArgumentException('Watch aggregation limits must be positive');
        }

        $this->notifyTimeoutSeconds = $notifyTimeoutSeconds === null
            ? self::defaultNotifyTimeoutSeconds($this->notifyDelaySeconds)
            : max($notifyTimeoutSeconds, $this->notifyDelaySeconds);
    }

    public static function defaultNotifyTimeoutSeconds(int $notifyDelaySeconds): int
    {
        if ($notifyDelaySeconds < 10) {
            return $notifyDelaySeconds * 6;
        }
        if ($notifyDelaySeconds < 60) {
            return 60;
        }

        return $notifyDelaySeconds;
    }

    public function markItemStarted(string $path): void
    {
        $this->inProgress[self::normalizePath($path)] = true;
    }

    public function markItemFinished(string $path): void
    {
        unset($this->inProgress[self::normalizePath($path)]);
    }

    public function clearInProgress(): void
    {
        $this->inProgress = [];
    }

    public function recordEvent(string $path, string $eventType = self::EVENT_NON_REMOVE, ?int $now = null): void
    {
        $now = self::clock($now);
        $path = self::normalizePath($path);
        $eventType = self::normalizeEventType($eventType);

        if (isset($this->inProgress[$path])) {
            return;
        }

        if ($this->events === [] && $this->notifyAt === null) {
            $this->notifyAt = $now + $this->notifyDelaySeconds;
        }

        if (isset($this->events['.'])) {
            return;
        }

        if ($path === '.' || count($this->events) >= $this->maxFiles) {
            $this->aggregateAt('.', $eventType, $now);
            return;
        }

        if (isset($this->events[$path])) {
            $this->updateEvent($path, $eventType, $now);
            return;
        }

        $ancestor = $this->trackedAncestor($path);
        if ($ancestor !== null) {
            $this->updateEvent($ancestor, $eventType, $now);
            return;
        }

        $parent = self::parentPath($path);
        $directChild = self::directChildName($path, $parent);
        $limit = $parent === '.' ? $this->maxFiles : $this->maxFilesPerDir;
        if (!$this->directChildExists($parent, $directChild) && $this->directChildCount($parent) >= $limit) {
            $this->aggregateAt($parent, $eventType, $now);
            return;
        }

        if ($this->descendantPaths($path) !== []) {
            $this->aggregateAt($path, $eventType, $now);
            return;
        }

        $this->events[$path] = [
            'path' => $path,
            'eventType' => $eventType,
            'firstAt' => $now,
            'lastAt' => $now,
        ];
    }

    /**
     * @return list<array{eventType:string, paths:list<string>, count:int}>
     */
    public function dueBatches(?int $now = null): array
    {
        $now = self::clock($now);
        if ($this->events === []) {
            $this->notifyAt = null;
            return [];
        }
        if ($this->notifyAt !== null && $now < $this->notifyAt) {
            return [];
        }

        $oldEvents = $this->popOldEvents($now, true);
        if ($this->notifyDelaySeconds !== $this->notifyTimeoutSeconds && !$this->hasNonRemoveEvents() && $this->hasRemoveBucketEvents()) {
            $oldEvents = array_replace($oldEvents, $this->popOldEvents($now, false));
        }

        if ($oldEvents === []) {
            $this->notifyAt = $now + $this->notifyDelaySeconds;
            return [];
        }

        $this->notifyAt = $this->events === [] ? null : $now + $this->notifyDelaySeconds;

        return self::eventBatches($oldEvents);
    }

    /**
     * @return array{pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool}
     */
    public function status(?int $now = null): array
    {
        $now = self::clock($now);
        $pendingTypes = [];
        foreach ($this->sortedEvents($this->events) as $path => $event) {
            $pendingTypes[$path] = $event['eventType'];
        }

        $inProgress = array_keys($this->inProgress);
        sort($inProgress, SORT_STRING);

        return [
            'pendingEventCount' => count($this->events),
            'pendingPaths' => array_keys($pendingTypes),
            'pendingTypes' => $pendingTypes,
            'inProgressPaths' => $inProgress,
            'notifyDelaySeconds' => $this->notifyDelaySeconds,
            'notifyTimeoutSeconds' => $this->notifyTimeoutSeconds,
            'nextScanAt' => $this->notifyAt,
            'due' => $this->wouldDispatchAt($now),
        ];
    }

    /**
     * @param array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}> $events
     * @return array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}>
     */
    private function sortedEvents(array $events): array
    {
        ksort($events, SORT_STRING);

        return $events;
    }

    /**
     * @return array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}>
     */
    private function popOldEvents(int $now, bool $delayRemoves): array
    {
        $oldEvents = [];
        foreach ($this->events as $path => $event) {
            if (!$this->isOld($event, $now, $delayRemoves)) {
                continue;
            }

            $oldEvents[$path] = $event;
            unset($this->events[$path]);
        }

        return $oldEvents;
    }

    /**
     * @param array{path:string, eventType:string, firstAt:int, lastAt:int} $event
     */
    private function isOld(array $event, int $now, bool $delayRemoves): bool
    {
        if (
            (!$delayRemoves || $event['eventType'] === self::EVENT_NON_REMOVE)
            && 2 * ($now - $event['lastAt']) > $this->notifyDelaySeconds
        ) {
            return true;
        }

        return $now - $event['firstAt'] > $this->notifyTimeoutSeconds;
    }

    private function updateEvent(string $path, string $eventType, int $now): void
    {
        $this->events[$path]['eventType'] = self::mergeEventTypes($this->events[$path]['eventType'], $eventType);
        $this->events[$path]['lastAt'] = $now;
    }

    private function aggregateAt(string $path, string $eventType, int $now): void
    {
        $firstAt = $now;
        foreach ($this->events as $existingPath => $event) {
            if ($existingPath !== $path && !self::isDescendantPath($existingPath, $path)) {
                continue;
            }

            $firstAt = min($firstAt, $event['firstAt']);
            $eventType = self::mergeEventTypes($eventType, $event['eventType']);
            unset($this->events[$existingPath]);
        }

        if ($path === '.') {
            $this->events = [];
        }

        $this->events[$path] = [
            'path' => $path,
            'eventType' => $eventType,
            'firstAt' => $firstAt,
            'lastAt' => $now,
        ];
    }

    private function trackedAncestor(string $path): ?string
    {
        $parent = self::parentPath($path);
        while ($parent !== null) {
            if (isset($this->events[$parent])) {
                return $parent;
            }
            if ($parent === '.') {
                return null;
            }
            $parent = self::parentPath($parent);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function descendantPaths(string $path): array
    {
        $children = [];
        foreach (array_keys($this->events) as $eventPath) {
            if (self::isDescendantPath($eventPath, $path)) {
                $children[] = $eventPath;
            }
        }

        return $children;
    }

    private function directChildCount(string $parent): int
    {
        $children = [];
        foreach (array_keys($this->events) as $eventPath) {
            $child = self::directChildName($eventPath, $parent);
            if ($child !== null) {
                $children[$child] = true;
            }
        }

        return count($children);
    }

    private function directChildExists(string $parent, ?string $child): bool
    {
        if ($child === null) {
            return false;
        }

        foreach (array_keys($this->events) as $eventPath) {
            if (self::directChildName($eventPath, $parent) === $child) {
                return true;
            }
        }

        return false;
    }

    private function hasNonRemoveEvents(): bool
    {
        foreach ($this->events as $event) {
            if ($event['eventType'] === self::EVENT_NON_REMOVE) {
                return true;
            }
        }

        return false;
    }

    private function hasRemoveBucketEvents(): bool
    {
        foreach ($this->events as $event) {
            if ($event['eventType'] !== self::EVENT_NON_REMOVE) {
                return true;
            }
        }

        return false;
    }

    private function wouldDispatchAt(int $now): bool
    {
        if ($this->events === [] || ($this->notifyAt !== null && $now < $this->notifyAt)) {
            return false;
        }

        $remaining = $this->events;
        $oldEvents = [];
        foreach ($remaining as $path => $event) {
            if ($this->isOld($event, $now, true)) {
                $oldEvents[$path] = $event;
                unset($remaining[$path]);
            }
        }

        if ($oldEvents !== []) {
            return true;
        }

        return !$this->eventsHaveNonRemove($remaining) && $this->eventsHaveRemoveBucket($remaining);
    }

    /**
     * @param array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}> $events
     */
    private function eventsHaveNonRemove(array $events): bool
    {
        foreach ($events as $event) {
            if ($event['eventType'] === self::EVENT_NON_REMOVE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}> $events
     */
    private function eventsHaveRemoveBucket(array $events): bool
    {
        foreach ($events as $event) {
            if ($event['eventType'] !== self::EVENT_NON_REMOVE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{path:string, eventType:string, firstAt:int, lastAt:int}> $events
     * @return list<array{eventType:string, paths:list<string>, count:int}>
     */
    private static function eventBatches(array $events): array
    {
        $groups = [
            self::EVENT_NON_REMOVE => [],
            self::EVENT_MIXED => [],
            self::EVENT_REMOVE => [],
        ];
        foreach ($events as $path => $event) {
            $groups[$event['eventType']][] = $path;
        }

        $batches = [];
        foreach ($groups as $eventType => $paths) {
            if ($paths === []) {
                continue;
            }
            sort($paths, SORT_STRING);
            $batches[] = [
                'eventType' => $eventType,
                'paths' => $paths,
                'count' => count($paths),
            ];
        }

        return $batches;
    }

    private static function normalizePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Watch event path must not contain NUL bytes');
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || $path === '.' || $path === '/') {
            return '.';
        }

        $path = ltrim($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException('Watch event path must not traverse above the root');
            }
            $parts[] = $part;
        }

        return $parts === [] ? '.' : implode('/', $parts);
    }

    private static function normalizeEventType(string $eventType): string
    {
        $eventType = strtolower(str_replace('_', '-', trim($eventType)));
        if ($eventType === 'nonremove') {
            $eventType = self::EVENT_NON_REMOVE;
        }
        if (!in_array($eventType, [self::EVENT_NON_REMOVE, self::EVENT_REMOVE, self::EVENT_MIXED], true)) {
            throw new \InvalidArgumentException('Unknown watch event type: ' . $eventType);
        }

        return $eventType;
    }

    private static function mergeEventTypes(string $left, string $right): string
    {
        return $left === $right ? $left : self::EVENT_MIXED;
    }

    private static function parentPath(string $path): ?string
    {
        if ($path === '.') {
            return null;
        }
        if (!str_contains($path, '/')) {
            return '.';
        }

        return substr($path, 0, strrpos($path, '/')) ?: '.';
    }

    private static function directChildName(string $path, string $parent): ?string
    {
        if ($parent === '.') {
            return $path === '.' ? null : explode('/', $path, 2)[0];
        }
        if ($path === $parent || !str_starts_with($path, $parent . '/')) {
            return null;
        }

        $rest = substr($path, strlen($parent) + 1);
        return explode('/', $rest, 2)[0];
    }

    private static function isDescendantPath(string $path, string $parent): bool
    {
        return $parent === '.'
            ? $path !== '.'
            : str_starts_with($path, $parent . '/');
    }

    private static function clock(?int $now): int
    {
        $now ??= time();
        if ($now < 0) {
            throw new \InvalidArgumentException('Watch event clock must not be negative');
        }

        return $now;
    }
}

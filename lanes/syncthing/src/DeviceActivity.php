<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class DeviceActivity
{
    /**
     * @var array<string, int>
     */
    private array $activity = [];

    /**
     * Returns the index of the least busy candidate, or -1 when no candidate exists.
     *
     * @param list<Availability> $availability
     */
    public function leastBusyIndex(array $availability): int
    {
        $lowestUsage = PHP_INT_MAX;
        $best = -1;

        foreach ($availability as $index => $candidate) {
            if (!$candidate instanceof Availability) {
                throw new \InvalidArgumentException('Expected only Availability instances');
            }

            $usage = $this->activity[$candidate->deviceId] ?? 0;
            if ($usage < $lowestUsage) {
                $lowestUsage = $usage;
                $best = $index;
            }
        }

        return $best;
    }

    /**
     * @param list<Availability> $availability
     */
    public function leastBusy(array $availability): ?Availability
    {
        $index = $this->leastBusyIndex($availability);

        return $index === -1 ? null : $availability[$index];
    }

    public function using(Availability $availability): void
    {
        $this->activity[$availability->deviceId] = ($this->activity[$availability->deviceId] ?? 0) + 1;
    }

    public function done(Availability $availability): void
    {
        $this->activity[$availability->deviceId] = ($this->activity[$availability->deviceId] ?? 0) - 1;
    }

    public function usage(string $deviceId): int
    {
        if ($deviceId === '') {
            throw new \InvalidArgumentException('Device ID must not be empty');
        }

        return $this->activity[$deviceId] ?? 0;
    }
}

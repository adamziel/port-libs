<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

interface PromisorObjectResolver
{
    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject;
}

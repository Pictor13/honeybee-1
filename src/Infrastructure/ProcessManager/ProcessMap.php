<?php

namespace Honeybee\Infrastructure\ProcessManager;

use Trellis\Common\Collection\TypedMap;
use Trellis\Common\Collection\UniqueCollectionInterface;

class ProcessMap extends TypedMap implements UniqueCollectionInterface
{
    public function getByName($process_name)
    {
        if (!$this->hasKey($process_name)) {
            throw new RuntimeError('Unable to find state-machine for name: ' . $process_name);
        }

        return $this->getItem($process_name);
    }

    protected function getItemImplementor()
    {
        return ProcessInterface::CLASS;
    }
}
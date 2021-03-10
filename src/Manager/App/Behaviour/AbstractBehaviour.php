<?php

namespace Manager\App\Behaviour;

use Manager\Domain\Instance;
use Manager\Domain\BehaviourInterface;
use Manager\Infra\Filesystem\ManagerFilesystem;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
abstract class AbstractBehaviour  implements BehaviourInterface
{
    public array $data = [];

    /**
     * TTLs are minutes.
     *
     * `false` to disable update.
     * `true` to always update (each cron exec).
     */
    public $cronTtl = false;
    public $instanceTtl = false;

    public function __construct()
    {
        $this->data = ManagerFilesystem::getBehaviourData($this);
    }

    public function updateFromCron(): void
    {
        $this->data['cron_last_update'] = (new \DateTimeImmutable())->format('c');
    }

    public function updateInstance(Instance $instance): Instance
    {
        $this->data['instances_last_updates'][$instance->slug] = (new \DateTimeImmutable())->format('c');

        return $instance;
    }

    public function write(): void
    {
        ManagerFilesystem::writeBehaviourData($this);
    }

    public function needsCronUpdate(): bool
    {
        return $this->needsUpdate($this->data['cron_last_update'] ?? null, $this->cronTtl);
    }

    public function needsInstanceUpdate(Instance $instance): bool
    {
        return $this->needsUpdate($this->data['instances_last_updates'][$instance->slug] ?? null, $this->instanceTtl);
    }

    private function needsUpdate(string $lastUpdate = null, $ttl): bool
    {
        if (is_bool($ttl)) {
            return $ttl;
        }

        if (null === $lastUpdate) {
            return true;
        }

        $lastUpdate = new \DateTimeImmutable($lastUpdate);
        $limitTtl = (new \DateTime)->sub(new \DateInterval(sprintf('PT%dM', $ttl)));

        return $lastUpdate < $limitTtl;
    }
}

<?php

namespace Manager\Domain;

use Manager\Domain\Instance;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
interface BehaviourInterface
{
    public function getSlug(): string;

    public function updateFromCron(): void;

    public function updateInstance(Instance $instance): Instance;
}

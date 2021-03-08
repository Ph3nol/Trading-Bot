<?php

namespace Manager\Domain\Exception;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceNotFoundException extends \Exception
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('Instance `%s` is not found', $slug));
    }
}

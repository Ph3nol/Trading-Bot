<?php

namespace Manager\Domain\Exception;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceNotFoundConfigFileException extends \Exception
{
    public function __construct(string $fileName)
    {
        parent::__construct(sprintf('Instance configuration file `%s` is not found', $fileName));
    }
}

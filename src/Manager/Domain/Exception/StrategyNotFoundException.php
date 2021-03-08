<?php

namespace Manager\Domain\Exception;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class StrategyNotFoundException extends \Exception
{
    public function __construct(string $strategyName)
    {
        parent::__construct(sprintf('Strategy `%s` is not found into strategies folder', $strategyName));
    }
}

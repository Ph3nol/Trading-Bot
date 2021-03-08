<?php

namespace Manager\UI\Console;

use Manager\App\InstanceHandler;
use Manager\App\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstancesStatusCommand extends BaseCommand
{
    protected static $defaultName = 'status';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        foreach ($manager->getInstances() as $instance) {
            InstanceHandler::init($instance);
        }

        $this->renderInstancesTable($manager->getInstances(), $output);

        return Command::SUCCESS;
    }
}

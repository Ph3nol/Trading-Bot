<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceStopCommand extends BaseCommand
{
    protected static $defaultName = 'stop';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        $instance = $this->askForInstance($input, $output);
        if (null === $instance) {
            return Command::SUCCESS;
        }
        $handler = InstanceHandler::init($instance);

        $output->writeln('Stopping the instance...');
        $handler->stop();
        $output->writeln('🎉 <info>Instance has been stopped!</info>');

        return Command::SUCCESS;
    }
}

<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstancesResetDataCommand extends BaseCommand
{
    protected static $defaultName = 'reset';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        $instance = $this->askForInstance($input, $output);
        if (null === $instance) {
            return Command::SUCCESS;
        }
        $handler = InstanceHandler::init($instance);

        if (false === $input->getOption('no-interaction')) {
            $this->renderInstancesTable([$instance], $output);

            $output->writeln('You gonna reset this instance data, which will stop it (if running) and definitely erase its data (irrevocable).');

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you confirm? [y/N] ', false);

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            $output->writeln('');
        }

        if ($instance->isRunning()) {
            $handler->stop();
        }

        $output->writeln('Resetting instance data...');

        $output->write('  Main instance data... ');
        $handler = InstanceHandler::init($instance);
        $handler->reset();
        $output->writeln('✅');

        $behaviours = $manager->getBehaviours();
        if ($behaviours) {
            $output->write('  Behaviours instance data... ');

            foreach ($behaviours as $behaviour) {
                $behaviourName = ucfirst($behaviour->getSlug());
                $behaviour->resetInstance($instance);
                $output->writeln('✅');
                $behaviour->write();
            }
        }
        $output->writeln('🎉 <info>Instance data has been resetted!</info>');

        return Command::SUCCESS;
    }
}

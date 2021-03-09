<?php

namespace Manager\UI\Console;

use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class TradeCommand extends BaseCommand
{
    protected static $defaultName = 'trade';

    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('no-ui', null, InputOption::VALUE_OPTIONAL, 'To disable UI and avoid its Docker container creation', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $instance = $this->askForInstance($input, $output);
        if (null === $instance) {
            return Command::SUCCESS;
        }
        $handler = InstanceHandler::init($instance);

        if (false === $input->getOption('no-interaction')) {
            $this->renderInstancesTable([$instance], $output);

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('You gonna trade with this instance, do you confirm? [y/N] ', false);

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            $output->writeln('');
        }

        $managerConfig = MANAGER_CONFIGURATION;
        $updatePairList = $managerConfig['update_pairlist'] ?? false;
        $stepsCount = 1;
        if ($instance->isRunning()) {
            $stepsCount++;
        }
        if ($updatePairList) {
            $stepsCount++;
        }

        $progressBar = new ProgressBar($output, $stepsCount);
        $progressBar->setFormat('withDescription');
        $progressBar->setMessage('Preparing...');
        $progressBar->start();

        if ($updatePairList) {
            $progressBar->setMessage('Updating exchange white pairlist...');
            $progressBar->advance();
            $handler->updateConfigPairlist();
        }

        if ($instance->isRunning()) {
            $progressBar->setMessage('Stopping running Docker instance...');
            $progressBar->advance();
            $handler->stop();
        }

        $withUI = (false === $input->getOption('no-ui'));
        $progressBar->setMessage(sprintf('Launching instance from Docker...%s', $withUI ? ' (+ UI instance)': ''));
        $progressBar->advance();
        $dockerIds = $handler->trade($withUI);

        $progressBar->finish();

        $output->writeln('');
        $output->writeln('🎉 <info>Instance is launched!</info>');
        if ($dockerIds['core']) {
            $output->writeln(sprintf('> Core Docker container ID: <comment>%s</comment>', $dockerIds['core']));
        }
        if ($dockerIds['ui']) {
            $output->writeln(sprintf('> UI Docker container ID: <comment>%s</comment>', $dockerIds['ui']));
        }

        $output->writeln('');
        $this->renderInstancesTable([$instance], $output);

        return Command::SUCCESS;
    }
}

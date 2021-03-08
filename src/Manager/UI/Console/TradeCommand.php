<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class TradeCommand extends BaseCommand
{
    protected static $defaultName = 'trade';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $instance = $this->getInstanceSlug($input, $output);
        if (null === $instance) {
            return Command::SUCCESS;
        }

        if (false === $input->getOption('no-interaction')) {
            $this->renderInstancesTable([$instance], $output);

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('You gonna trade with this instance, do you confirm? [y/N] ', false);

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            $output->writeln('');
        }

        $progressBar = new ProgressBar($output, 3);
        $progressBar->setFormat('withDescription');
        $progressBar->start();

        $progressBar->setMessage('Preparing instance...');
        $progressBar->advance();
        $handler = InstanceHandler::init($instance);

        // $progressBar->setMessage('Updating exchange white pairlist...');
        // $progressBar->advance();
        // $handler->updateConfigPairlist();

        if ($instance->isCoreRunning()) {
            $progressBar->setMessage('Stopping running Docker instance...');
            $progressBar->advance();
            $dockerIds = $handler->stop();
        }

        $progressBar->setMessage('Launching instance from Docker...');
        $progressBar->advance();
        $dockerIds = $handler->trade();

        $progressBar->finish();

        $output->writeln('');
        $output->writeln('🎉 <info>Instance is launched!</info>');
        if ($dockerIds['core']) {
            $output->writeln(sprintf('- Core Docker container ID: <comment>%s</comment>', $dockerIds['core']));
        }
        if ($dockerIds['ui']) {
            $output->writeln(sprintf('- UI Docker container ID: <comment>%s</comment>', $dockerIds['ui']));
        }

        $output->writeln('');
        $this->renderInstancesTable([$instance], $output);

        return Command::SUCCESS;
    }
}

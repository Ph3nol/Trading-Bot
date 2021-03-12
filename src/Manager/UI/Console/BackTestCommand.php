<?php

namespace Manager\UI\Console;

use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class BackTestCommand extends BaseCommand
{
    protected static $defaultName = 'backtest';

    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('--no-download', null, InputOption::VALUE_OPTIONAL, 'Disable data download and use already grabbed one', false)
            ->addOption('--days', null, InputOption::VALUE_OPTIONAL, 'Days count', 5)
            ->addOption('--fee', null, InputOption::VALUE_OPTIONAL, 'Fee', 0.001)
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

        if (false === $input->getOption('no-download')) {
            $output->writeln('⚙️  Downloading backtest data...');
            $handler->backtestDownloadData((int) $input->getOption('days'));
            $output->writeln('');
        }

        $output->writeln('⚙️  Backtesting...');
        $backtestOutput = $handler->backtest((float) $input->getOption('fee'));
        $output->writeln($backtestOutput);

        $output->writeln('');
        $output->writeln('🎉 <info>Done!</info>');

        return Command::SUCCESS;
    }
}

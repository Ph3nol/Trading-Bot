<?php

namespace Manager\UI\Console;

use Manager\Domain\Instance;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Symfony\Component\Console\Input\InputOption;
use Manager\App\Behaviour\TradingViewScanBehaviour;
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

        $daysCount = (int) $input->getOption('days');
        $output->writeln(sprintf('⚙️  Getting instance pairs, period of %d day(s)...', $daysCount));
        $this->generatePairsAndUpdateInstance($instance, $daysCount);

        if (false === $input->getOption('no-download')) {
            $output->writeln('⚙️  Downloading backtest data...');
            $handler->backtestDownloadData((int) $input->getOption('days'));
        }

        $output->writeln('⚙️  Backtesting...');
        $backtestOutput = $handler->backtest((float) $input->getOption('fee'));
        $output->writeln($backtestOutput);

        $output->writeln('');
        $output->writeln('🎉 <info>Done!</info>');

        return Command::SUCCESS;
    }

    private function generatePairsAndUpdateInstance(Instance $instance, int $daysCount): void
    {
        $pairsBehaviour = new TradingViewScanBehaviour();
        $pairs = $pairsBehaviour->scrapPairlistsFromTW(
            sprintf(
                '{"filter":[{"left":"change","operation":"nempty"}],"options":{"active_symbols_only":true,"lang":"fr"},"symbols":{"query":{"types":[]},"tickers":[]},"columns":["base_currency_logoid","currency_logoid","name","exchange"],"sort":{"sortBy":"change|%dD","sortOrder":"desc"},"range":[0,2000]}',
                $daysCount
            )
        );

        $exchangeKey = strtoupper($instance->config['exchange']['name']);
        $pairList = $pairs[$exchangeKey][$instance->config['stake_currency']] ?? [];

        $instance->updateStaticPairList(
            array_unique($pairList)
        );

        InstanceFilesystem::writeInstanceConfigBacktest($instance);
    }
}

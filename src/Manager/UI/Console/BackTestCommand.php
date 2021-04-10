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
            ->addOption('--plotting', null, InputOption::VALUE_OPTIONAL, 'Plotting', false)
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
        InstanceFilesystem::writeInstanceConfigBacktest($instance);

        if (false === $input->getOption('no-download')) {
            $daysCount = (int) $input->getOption('days');
            $output->writeln(sprintf('⚙️  Getting instance pairs, period of %d day(s)...', $daysCount));
            $this->generatePairsAndUpdateInstance($handler, $daysCount);

            $output->writeln('⚙️  Downloading backtest data...');
            $handler->backtestDownloadData((int) $input->getOption('days'));
        }

        $output->writeln('⚙️  Backtesting...');
        $backtestOutput = $handler->backtest((float) $input->getOption('fee'));
        $output->writeln($backtestOutput);

        $plottingCount = $input->getOption('plotting');
        if (false !== $plottingCount) {
            $output->writeln('⚙️  Plotting...');

            $plottingPairs = [];
            if (null !== $plottingCount) {
                $plottingPairs = $instance->config['exchange']['pair_whitelist'];
                shuffle($plottingPairs);
                $plottingPairs = array_slice($plottingPairs, 0, $plottingCount);
            }

            $handler->removePlottingData();
            $handler->plot($plottingPairs);
        }

        $output->writeln('');
        $output->writeln('🎉 <info>Done!</info>');

        return Command::SUCCESS;
    }

    private function generatePairsAndUpdateInstance(InstanceHandler $instanceHandler, int $daysCount): void
    {
        $instance = $instanceHandler->getInstance();
        // $pairList = $this->fetchPairsFromTradingViewBehaviour($instance, $daysCount);
        $pairList = $this->fetchPairsFromConfig($instanceHandler, $daysCount);

        $instance->updateStaticPairList(array_unique($pairList));

        InstanceFilesystem::writeInstanceConfigBacktest($instance);
    }

    private function fetchPairsFromConfig(InstanceHandler $instanceHandler, int $daysCount): array
    {
        return $instanceHandler->getPairsList();
    }

    private function fetchPairsFromTradingViewBehaviour(InstanceHandler $instanceHandler, int $daysCount): array
    {
        $instance = $instanceHandler->getInstance();

        $pairsBehaviour = new TradingViewScanBehaviour();
        $requestPayload = <<<EOF
            {
                "filter": [
                    {
                        "left": "change",
                        "operation": "nempty"
                    },
                    {
                        "left": "change",
                        "operation": "greater",
                        "right": 0
                    }
                ],
                "options": {
                    "active_symbols_only": true,
                    "lang": "fr"
                },
                "columns": [
                    "name",
                    "exchange",
                    "change"
                ],
                "sort": {
                    "sortBy": "change|%dD",
                    "sortOrder": "desc"
                },
                "range": [
                    0,
                    5000
                ]
            }
        EOF;
        $requestPayload = sprintf($requestPayload, $daysCount);

        $pairs = $pairsBehaviour->scrapPairlistsFromTW(
            sprintf($requestPayload, $daysCount)
        );

        $exchangeKey = strtoupper($instance->config['exchange']['name']);

        return $pairs[$exchangeKey][$instance->config['stake_currency']] ?? [];
    }
}

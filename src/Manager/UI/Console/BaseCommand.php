<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\Domain\Instance;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Helper\Table;
use Manager\Infra\Process\InstanceUIProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class BaseCommand extends Command
{
    const CONFIGURATION_FILE_PATH_DEFAUT = '/manager/config.yaml';
    const INSTANCE_FOLDER_PATH_DEFAUT = '/manager/instances';

    protected function configure()
    {
        $this
            ->addArgument('instance', InputArgument::OPTIONAL, 'Instance name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputStyle = new OutputFormatterStyle('white', 'red', ['blink']);
        $output->getFormatter()->setStyle('warning', $outputStyle);

        $outputStyle = new OutputFormatterStyle('red', null);
        $output->getFormatter()->setStyle('danger', $outputStyle);

        $outputStyle = new OutputFormatterStyle('#888');
        $output->getFormatter()->setStyle('muted', $outputStyle);

        $outputStyle = new OutputFormatterStyle(null, null, ['reverse']);
        $output->getFormatter()->setStyle('reverse', $outputStyle);

        ProgressBar::setFormatDefinition('withDescription', '⚙️  %current%/%max% --- <comment>%message%</comment>');
    }

    protected function renderInstancesTable(array $instances, OutputInterface $output)
    {
        $statusData = [];
        $instanceIndex = 1;
        foreach ($instances as $instance) {
            $components = $this->getInstanceComponents($instance);
            $containers = $this->getInstanceContainers($instance);
            $informations = $this->getInstanceInformations($instance);

            $statusData[] = [
                sprintf(
                    "<comment>%s</comment>\n%s",
                    (string) $instance,
                    $instance->isProduction() ? '<warning>PRODUCTION</warning>' : '<reverse>DRY-RUN</reverse>'
                ),
                implode("\n", $informations),
                implode("\n", $containers),
                implode("\n", $components),
            ];

            if ($instanceIndex < count($instances)) {
                $statusData[] = new TableSeparator();
            }

            $instanceIndex++;
        }

        $table = new Table($output);
        $table
            ->setHeaders([
                'Instance',
                'Configuration',
                new TableCell('Components', ['colspan' => 2])
            ])
            ->setRows($statusData);
        $table->setStyle('box-double');
        $table->render();
    }

    protected function askForInstance(InputInterface $input, OutputInterface $output): ?Instance
    {
        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        if ($instanceSlug = $input->getArgument('instance')) {
            return $manager->findRequiredInstanceFromSlug($instanceSlug);
        }

        $instancesData = [];
        $instances = array_values($manager->getInstances());
        foreach ($instances as $k => $instance) {
            InstanceHandler::init($instance);

            $instancesData[] = [
                sprintf('[<info>%d</info>]', $k +1),
                (string) $instance,
                $instance->isRunning() ? '<info>▇</info>' : '<danger>▇</danger>'
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['', 'Instance', 'Status'])
            ->setRows($instancesData);
        $table->setStyle('box-double');
        $table->render();

        $helper = $this->getHelper('question');
        $question = new Question('Which instance is concerned? (<comment>0</comment> to <comment>CANCEL</comment>) --> ');
        $choiceNumber = $helper->ask($input, $output, $question);
        if ('0' === $choiceNumber || 'cancel' === strtolower($choiceNumber)) {
            return null;
        }

        $instance = $instances[(int) $choiceNumber - 1] ?? null;
        if (null === $instance) {
            $output->writeln('<error>Invalid choice</error>');

            return null;
        }

        return $instance;
    }

    private function getInstanceInformations(Instance $instance): array
    {
        return [
            sprintf('<comment>%s</comment>', $instance->strategy),
            sprintf(
                '-> Stakes: %s %f %s',
                (-1 === $instance->config['max_open_trades']) ? 'Unlimited' : $instance->config['max_open_trades'] ?? 0 . ' x',
                $instance->config['stake_amount'] ?? 0,
                $instance->config['stake_currency'] ?? 'BTC'
            ),
            sprintf('-> DRW: %f %s', $instance->config['dry_run_wallet'] ?? 0, $instance->config['stake_currency'] ?? 'BTC'),
            sprintf('-> Behaviours: %s', implode(', ', array_keys($instance->behaviours))),
        ];
    }

    private function getInstanceContainers(Instance $instance): array
    {
        $managerConfig = MANAGER_CONFIGURATION;

        $containers = [];
        $containers[] = sprintf(
            '%s Core%s',
            $instance->isRunning() ? '<info>▇</info>' : '<danger>▇</danger>',
            ($instance->isRunning() && $instance->uptime) ? sprintf(' (%s)', $instance->uptime) : ''
        );
        $containers[] = sprintf(
            '%s <href=http://%s:%d/trade>UI</>%s',
            $instance->isUIRunning() ? '<info>▇</info>' : '<danger>▇</danger>',
            $managerConfig['hosts']['ui'],
            $instance->parameters['ports']['ui'],
            ($instance->isUIRunning() && $instance->uiUptime) ? sprintf(' (%s)', $instance->uiUptime) : ''
        );

        return $containers;
    }

    private function getInstanceComponents(Instance $instance): array
    {
        $managerConfig = MANAGER_CONFIGURATION;

        $components = [];
        $components[] = sprintf(
            '%s <href=http://%s:%d/api/v1/ping>API Server</>',
            $instance->isApiEnabled() ? '<info>▇</info>' : '<muted>-</muted>',
            $managerConfig['hosts']['api'],
            $instance->parameters['ports']['api']
        );
        $components[] = sprintf('%s Edge', $instance->isEdgeEnabled() ? '<info>▇</info>' : '<muted>-</muted>');
        $components[] = sprintf('%s Telegram', $instance->isTelegramEnabled() ? '<info>▇</info>' : '<muted>-</muted>');
        $components[] = sprintf('%s Force-Buy', $instance->isForceBuyEnabled() ? '<info>▇</info>' : '<muted>-</muted>');

        return $components;
    }
}

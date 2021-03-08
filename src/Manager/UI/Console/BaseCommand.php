<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\Domain\Instance;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputOption;
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
            $informations = $this->getInstanceInformations($instance);

            $statusData[] = [
                sprintf(
                    "<comment>%s</comment>\n<muted>%s</muted>\n%s",
                    (string) $instance,
                    $instance->slug,
                    $instance->isProduction() ? '<warning>PRODUCTION</warning>' : '<reverse>DRY-RUN</reverse>'
                ),
                implode("\n", $informations),
                implode("\n", $components),
            ];

            if ($instanceIndex < count($instances)) {
                $statusData[] = new TableSeparator();
            }

            $instanceIndex++;
        }

        $table = new Table($output);
        $table
            ->setHeaders(['INSTANCE', 'INFORMATIONS', 'COMPONENTS'])
            ->setRows($statusData);
        $table->setStyle('box-double');
        $table->render();
    }

    protected function getInstanceSlug(InputInterface $input, OutputInterface $output): ?Instance
    {
        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        if ($instanceSlug = $input->getArgument('instance')) {
            return $manager->findRequiredInstanceFromSlug($instanceSlug);
        }

        $instances = $manager->getInstances();
        $instancesChoices = array_keys($instances);
        $cancelOption = '<comment>--- Cancel ---</comment>';
        array_unshift($instancesChoices, $cancelOption);

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Which instance is concerned? ',
            $instancesChoices
        );
        $question->setErrorMessage('Instance name is invalid!');

        $instanceSlug = $helper->ask($input, $output, $question);
        if ($cancelOption === $instanceSlug) {
            return null;
        }

        return $manager->findRequiredInstanceFromSlug($instanceSlug);
    }

    private function getInstanceInformations(Instance $instance): array
    {
        return [
            sprintf('<comment>%s</comment>', $instance->strategy),
            sprintf(
                '%s %f %s stakes',
                (-1 === $instance->config['max_open_trades']) ? 'Unlimited' : $instance->config['max_open_trades'] . ' x',
                $instance->config['stake_amount'],
                $instance->config['stake_currency']
            ),
            sprintf('DRW: %f %s', $instance->config['dry_run_wallet'], $instance->config['stake_currency']),
        ];
    }

    private function getInstanceComponents(Instance $instance): array
    {
        $components = [];

        $components[] = sprintf(
            '<muted>[ENABLED] </muted> Core         %s',
            $instance->isCoreRunning() ? '<info>▶ RUNNING ◀</info>' : '<danger>▶ STOPPED ◀</danger>'
        );

        $components[] = sprintf(
            '%s <href=http://api.%s:%d/api/v1/ping>Core/API</>',
            $instance->isApiEnabled() ? '<info>[ENABLED] </info>' : '<danger>[DISABLED]</danger>',
            MANAGER_PROJECT_DOMAIN,
            $instance->parameters['ports']['api']
        );

        $components[] = sprintf(
            '<info>[ENABLED] </info> <href=http://ui.%s:%d/trade>UI</>',
            MANAGER_PROJECT_DOMAIN,
            $instance->parameters['ports']['ui']
        );

        $components[] = sprintf('%s Telegram', $instance->isTelegramEnabled() ? '<info>[ENABLED] </info>' : '<danger>[DISABLED]</danger>');
        $components[] = sprintf('%s Force-Buy', $instance->isForceBuyEnabled() ? '<info>[ENABLED] </info>' : '<danger>[DISABLED]</danger>');

        return $components;
    }
}

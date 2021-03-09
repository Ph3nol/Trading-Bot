<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Helper\Table;
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

        $this->renderManagerParametersTable($manager, $output);
        $output->writeln('');
        $this->renderInstancesTable($manager->getInstances(), $output);

        return Command::SUCCESS;
    }

    private function renderManagerParametersTable(Manager $manager, OutputInterface $output): void
    {
        $managerParameters = $manager->getParameters();

        $paramsData = [
            [
                'Pairlist Update (24 volume %)',
                ($managerParameters['update_pairlist'] ?? false) ? '<info>▇</info>' : '<danger>▇</danger>',
            ],
            [
                'Base API host',
                $managerParameters['hosts']['api'],
            ],
            [
                'Base UI host',
                $managerParameters['hosts']['ui'],
            ],
            [
                'API CORS domains',
                implode(', ', $managerParameters['cors_domains'] ?? []),
            ],
        ];

        $table = new Table($output);
        $table
            ->setHeaders([
                'Parameter',
                'Value'
            ])
            ->setRows($paramsData);
        $table->setStyle('borderless');
        $table->render();
    }
}

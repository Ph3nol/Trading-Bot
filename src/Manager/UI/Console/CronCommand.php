<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\Domain\Instance;
use Symfony\Component\Console\Command\Command;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class CronCommand extends BaseCommand
{
    protected static $defaultName = 'cron';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');

        $output->writeln('⚙️  Updating behaviours...');
        foreach ($manager->getBehaviours() as $behaviour) {
            $behaviourName = ucfirst($behaviour->getSlug());

            $output->write(sprintf('    <comment>[%s]</comment> Main update... ', $behaviourName));
            if ($behaviour->needsCronUpdate()) {
                $behaviour->updateFromCron();
                $output->writeln('✅');
            } else {
                $output->writeln('⏺');
            }

            foreach ($manager->getInstances() as $instance) {
                $output->write(sprintf(
                    '    <comment>[%s]</comment> Instance `%s` update... ',
                    $behaviourName,
                    (string) $instance
                ));
                if ($behaviour->needsInstanceUpdate($instance)) {
                    $behaviour->updateInstance($instance);
                    InstanceFilesystem::writeInstanceConfig($instance);
                    $output->writeln('✅');
                } else {
                    $output->writeln('⏺');
                }
            }

            $behaviour->write();
        }

        $output->writeln('');
        $output->writeln('🎉 <info>Done!</info>');

        return Command::SUCCESS;
    }
}

<?php

namespace Manager\UI\Console;

use Manager\App\Manager;
use Manager\Domain\Instance;
use Manager\App\InstanceHandler;
use Symfony\Component\Console\Command\Command;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class CronCommand extends BaseCommand
{
    protected static $defaultName = 'cron';

    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('--crontab', null, InputOption::VALUE_OPTIONAL, 'Output the Crontab line', false)
            ->addOption('--only-instances', null, InputOption::VALUE_OPTIONAL, 'Instance updates only', false)
            ->addOption('--force', null, InputOption::VALUE_OPTIONAL, 'Force updates', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $manager = Manager::fromFile(MANAGER_DIRECTORY . '/manager.yaml');
        $forceUpdates = false !== $input->getOption('force');

        if (false !== $input->getOption('crontab')) {
            $output->writeln('This line is to add to your crontabs, in order to run periodic tasks needed by your instances and their behaviours.');
            $output->writeln('');
            $output->writeln(sprintf(
                '<comment>*/5 * * * * BOT_CONFIG_DIRECTORY=%s %s cron >> /tmp/trading-bot-manager-cron.log</comment>',
                HOST_MANAGER_DIRECTORY,
                HOST_BOT_SCRIPT_PATH
            ));

            return Command::SUCCESS;
        }

        $instancesToRestart = [];

        $output->writeln('⚙️  Updating...');
        foreach ($manager->getBehaviours() as $behaviour) {
            $behaviourName = ucfirst($behaviour->getSlug());

            if ($forceUpdates || false === $input->getOption('only-instances')) {
                $output->write(sprintf('    <comment>[%s]</comment> Main update... ', $behaviourName));
                if ($forceUpdates || $behaviour->needsCronUpdate()) {
                    $behaviour->updateCron();
                    $output->writeln('✅');
                } else {
                    $output->writeln('⏺');
                }
            }

            foreach ($manager->getInstances() as $instance) {
                $handler = InstanceHandler::init($instance);

                if (false === $instance->hasBehaviour($behaviour)) {
                    continue;
                }

                $output->write(sprintf(
                    '    <comment>[%s]</comment> @ <comment>[%s]</comment> Updating... ',
                    $behaviourName,
                    (string) $instance
                ));

                if ($forceUpdates || $behaviour->needsInstanceUpdate($instance)) {
                    $behaviour->updateInstanceFromCron($instance);
                    InstanceFilesystem::writeInstanceConfig($instance);
                    $instancesToRestart[] = $instance;
                    $output->writeln('✅');
                } else {
                    $output->writeln('⏺');
                }
            }

            $behaviour->write();
        }

        if ($instancesToRestart) {
            $output->writeln('');
            $output->writeln('⚙️  Restarting updated running instances...');
            foreach ($instancesToRestart as $instance) {
                $handler = InstanceHandler::init($instance);

                if ($instance->isRunning()) {
                    $output->write(sprintf(
                        '    <comment>[%s]</comment> Restarting instance `%s`... ',
                        $behaviourName,
                        (string) $instance
                    ));
                    $handler->restart(false);
                    $output->writeln('✅');
                }
            }
        }

        $output->writeln('');
        $output->writeln('🎉 <info>Done!</info>');

        return Command::SUCCESS;
    }
}

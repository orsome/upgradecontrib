<?php

namespace Orsome\Installer\Console;

use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeContribCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('upgradecontrib')
            ->setDescription('Upgrade contributed plugins');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $CFG;


        $updates = [];
        $pluginmanager = \core_plugin_manager::instance();
        $checker = \core\update\checker::instance();
        $checker->fetch();
        foreach ($pluginmanager->get_plugins() as $plugintype => $pluginnames) {
            foreach ($pluginnames as $pluginname => $pluginfo) {
                if (!$pluginfo->is_standard()) {
                    $component = $plugintype . '_' . $pluginname;
                    $updateinfo = $checker->get_update_info($component);
                    $updateinfo = is_array($updateinfo) ? array_shift($updateinfo) : $updateinfo;
                    if ($updateinfo) {
                        $data = [
                            "--name={$pluginname}",
                            "--md5={$updateinfo->downloadmd5}",
                            "--package={$updateinfo->download}",
                            "--typeroot={$pluginfo->typerootdir}"
                        ];
                        $updates[$component] = $data;
                    }
                }
            }
        }

        $builder = new ProcessBuilder();
        $builder->setPrefix((new PhpExecutableFinder())->find());
        $defaultarguments = [
            $CFG->dirroot . '/mdeploy.php',
            '--upgrade'
        ];

        foreach ($updates as $name => $arguments) {
            $arguments = array_merge($defaultarguments, $arguments);
            $builder->setArguments($arguments);

            $process = $builder
                ->getProcess();
            $process->setTimeout(3600);
            $output->writeln("<comment>Updating $name</comment>");
            $process->run(function ($type, $line) use ($output) {
                $output->write($line);
            });
        }

    }

    /**
     * Load Moodle.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists(getcwd().'/config.php')) {
            throw new RuntimeException('No config.php file found.');
        }
        define('CLI_SCRIPT', true);
        require_once(getcwd() . '/config.php');
        if (!isset($CFG->version) || !isset($CFG->allversionshash)) {
            throw new RuntimeException('I don\'t think this is Moodle or Totara.');
        }
        require_once($CFG->libdir . '/environmentlib.php');
        $release = normalize_version($CFG->release);

        if (!(version_compare($release, '2.4.0', '>') && version_compare($release, '3.0.0', '<'))) {
            throw new RuntimeException("{$release} not supported.");
        }
    }


}

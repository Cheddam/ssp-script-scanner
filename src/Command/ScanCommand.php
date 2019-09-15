<?php

namespace App\Command;

use App\Service\DeploynautAPIService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScanCommand extends Command
{
    const DATA_PATH = __DIR__ . '/../../data/';

    protected static $defaultName = 'scan';

    /**
     * @var DeploynautAPIService
     */
    private $api;

    /**
     * @var OutputInterface
     */
    private $output;

    protected function configure()
    {
        $this
            ->setDescription('Fetches scripts from environments and caches them. Use --flush to re-fetch.')
            ->setDefinition(
                new InputDefinition([
                    new InputOption('flush', 'f', InputOption::VALUE_OPTIONAL, '', false)
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->api = new DeploynautAPIService();
        $this->output = $output;

        $shouldFlush = $input->getOption('flush');

        $environments = $this->getEnvironmentList($shouldFlush);

        if (!count($environments)) {
            $this->output->writeln('<comment>No environments found. Exiting.</comment>');
            return;
        }

        $this->output->writeln('<info>Found ' . count($environments) . ' environment(s).</info>');

        $environments = $this->getScriptDataForEnvironments($environments, $shouldFlush);

        $envsWithScripts = array_filter($environments, function ($env) { return !empty($env['scripts']); });

        $encoded = json_encode($envsWithScripts);
        file_put_contents(self::DATA_PATH . 'environments-that-have-scripts.json', $encoded);

        $output->writeln('<info>Found scripts in ' . count($envsWithScripts) . '/' . count($environments) . ' environments.</info>');

        $envsWithPostInstallScripts = array_filter($envsWithScripts, function ($env) {
            return count(array_filter($env['scripts'], function ($script) { return $script['name'] === 'post-install-cmd'; }));
        });

        $encoded = json_encode($envsWithPostInstallScripts);
        file_put_contents(self::DATA_PATH . 'environments-that-have-post-install-scripts.json', $encoded);

        $output->writeln('<question>Found post-install scripts in ' . count($envsWithPostInstallScripts) . ' environments.</question>');

        foreach ($envsWithPostInstallScripts as $env) {
            $output->writeln(' - ' . $env['stack'] . '-' . $env['environment'] . ' :: ' . $_ENV['API_URL'] . 'project/' . $env['stack'] . '/environment/' . $env['environment'] . '/overview');

            foreach ($env['scripts'] as $script) {
                if ($script['name'] === 'post-install-cmd') {
                    if (is_string($script['scripts'])) {
                        $output->writeln('   - ' . $script['scripts']);
                    } elseif (is_array($script['scripts'])) {
                        foreach ($script['scripts'] as $cmd) {
                            $output->writeln('   - ' . $cmd);
                        }
                    }

                    break;
                }
            }
        }
    }

    /**
     * @param bool $shouldFlush
     * @return array
     */
    protected function getEnvironmentList(bool $shouldFlush = false): array
    {
        if (!file_exists(self::DATA_PATH . 'environments.json') || $shouldFlush) {
            $this->output->writeln('Fetching environments...');

            try {
                $stacks = $this->api->getStackList()['data'];

                $environments = [];
                foreach ($stacks as $stack) {
                    foreach ($stack['relationships']['environments']['data'] as $environment) {
                        $environments[] = ['stack' => $stack['id'], 'environment' => $environment['id']];
                    }
                }

                file_put_contents(self::DATA_PATH . 'environments.json', json_encode($environments));
            } catch (Exception $e) {
                $this->output->writeln('<error>Failed to retrieve environments. Please check API configuration and availability.</error>');
                return [];
            }
        } else {
            $this->output->writeln('Loading environments from cache...');
            $environments = json_decode(file_get_contents(self::DATA_PATH . 'environments.json'), true);
        }

        return $environments;
    }

    /**
     * @param array $environments
     * @param bool $shouldFlush
     * @return array
     */
    protected function getScriptDataForEnvironments(array $environments, bool $shouldFlush = false): array
    {
        if (!file_exists(self::DATA_PATH . 'environments-with-script-data.json') || $shouldFlush) {
            $this->output->writeln('Fetching scripts for environments...');

            $progressBar = new ProgressBar($this->output, count($environments));
            $failures = [];

            foreach ($environments as $i => $environment) {
                try {
                    $response = $this->api->getScriptsForEnvironment($environment['stack'], $environment['environment']);
                    $environments[$i]['scripts'] = $response['data'];

                    $this->output->write('.');
                } catch (Exception $e) {
                    // Can't get the scripts - not a major, but we'll tell the user later
                    $failures[] = $environment;
                }

                $progressBar->advance();

                // Avoid stressing the dashboard out
                sleep(1);
            }

            $progressBar->finish();
            $this->output->writeln('');

            if (count($failures)) {
                $this->output->writeln('<comment>Failed to fetch scripts for ' . count($failures) . 'environment(s).</comment>');
            }

            file_put_contents(self::DATA_PATH . 'environments-with-script-data.json', json_encode($environments));
        } else {
            $this->output->writeln('Loading scripts for environments from cache...');
            $environments = json_decode(file_get_contents(self::DATA_PATH . 'environments-with-script-data.json'), true);
        }
        return $environments;
    }
}
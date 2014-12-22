<?php
namespace Tinyploy;

use Phellow\Cli\Input;
use Phellow\Cli\Parameters;
use Phellow\Cli\Script;
use Phellow\Cli\SimpleTable;

/**
 * @author    Christian Blos <christian.blos@gmx.de>
 * @copyright Copyright (c) 2014-2015, Christian Blos
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @link      https://github.com/christianblos
 */
class App extends Script
{
    /**
     * @var array
     */
    private $configs = [];

    /**
     * @var Input
     */
    private $input;

    /**
     *
     */
    public function __construct()
    {
        $this->input = new Input();
    }

    /**
     * {@inheritdoc}
     */
    protected function initParams()
    {
        $params = new Parameters();
        $params->registerArgument('command');
        return $params;
    }

    /**
     * {@inheritdoc}
     */
    protected function run()
    {
        $command = $this->params->get('command');

        switch ($command) {
            case 'build':
                // ./tinyploy build <project> [<commit>]
                $project = $this->params->get(0);
                $commit = $this->params->get(1);
                $this->build($project, $commit);
                break;

            case 'deploy':
                // ./tinyploy deploy <project> <environment>
                $project = $this->params->get(0);
                $env = $this->params->get(1);
                $this->deploy($project, $env);
                break;

            case '':
                $this->showHelp($this->params);
                break;

            default:
                throw new \Exception('command "' . $command . '" not found');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function showHelp(Parameters $params)
    {
        $table = new SimpleTable();
        $table->border = false;
        $table->padding = 3;

        $this->outln('');
        $this->outln('./tinyploy build <project> [<commit>]');

        $table->addRow(
            [
                'project',
                'The configured project'
            ]
        );
        $table->addRow(
            [
                'commit',
                'Checkout this commit'
            ]
        );
        foreach ($table->getTable(true) as $row) {
            $this->outln("\t" . $row);
        }

        $table->clean();

        $this->outln('');
        $this->outln('./tinyploy deploy <project> <environment>');

        $table->addRow(
            [
                'project',
                'The configured project'
            ]
        );
        $table->addRow(
            [
                'environment',
                'The configured environment'
            ]
        );
        foreach ($table->getTable(true) as $row) {
            $this->outln("\t" . $row);
        }

        $this->outln('');
    }

    /**
     * Get path for project configs.
     *
     * @param string $project
     * @param string $file
     *
     * @return string
     */
    private function getProjectConfigFile($project, $file)
    {
        if (!preg_match('/^[0-9a-z_-]/i', $project)) {
            throw new \Exception('invalid project name "' . $project . '"');
        }

        $path = realpath(__DIR__ . '/../projects/' . $project . '/' . $file);
        if (!$path) {
            throw new \Exception('file "' . $file . '" for project "' . $project . '" does not exist');
        }
        return $path;
    }

    /**
     * @param string $project
     *
     * @return Config
     */
    private function loadConfig($project)
    {
        if (!isset($this->configs[$project])) {
            $configFile = $this->getProjectConfigFile($project, 'config.json');
            $data = json_decode(file_get_contents($configFile), true);

            if (!$data || !is_array($data)) {
                throw new \Exception('invalid config file');
            }

            $this->configs[$project] = new Config($data);
        }
        return $this->configs[$project];
    }

    /**
     * @param string $project
     * @param bool   $next
     *
     * @return string
     */
    protected function getBuildDir($project, $next = false)
    {
        $buildNumber = 0;

        $dir = APP_PATH . '/builds/' . $project;;
        if (is_dir($dir)) {
            foreach (scandir($dir) as $path) {
                if (preg_match('/^build-([0-9]+)$/', $path, $matches)) {
                    $num = (int)$matches[1];
                    if ($num > $buildNumber) {
                        $buildNumber = $num;
                    }
                }
            }
        }

        if ($next) {
            $buildNumber++;
        }

        if ($buildNumber > 0) {
            return $dir . '/build-' . $buildNumber;
        }
        return null;
    }

    /**
     * @param string $project
     * @param string $buildDir
     *
     * @return array
     */
    private function getCommandReplacements($project, $buildDir)
    {
        return [
            '${projectDir}' => $this->getProjectConfigFile($project, null),
            '${buildDir}'   => $buildDir,
        ];
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private function notify($message)
    {
        $this->outln(PHP_EOL . '> ' . $message);
    }

    /**
     * @param string $cmd
     * @param string $chdir
     * @param array  $replace
     *
     * @return void
     */
    private function runCommand($cmd, $chdir = null, $replace = [])
    {
        if ($chdir) {
            chdir($chdir);
        }

        if ($replace) {
            $cmd = str_replace(array_keys($replace), array_values($replace), $cmd);
        }

        $this->notify('run: ' . $cmd);
        passthru($cmd, $return);

        if ($return != 0) {
            throw new \Exception('Command failed!');
        }
    }

    /**
     * @param string $project
     * @param string $commit
     *
     * @return void
     */
    private function build($project, $commit)
    {
        $config = $this->loadConfig($project);
        $buildDir = $this->getBuildDir($project, true);

        $this->notify('clone repo');
        passthru('git clone ' . $config->repo . ' ' . $buildDir);

        if ($commit) {
            $this->notify('checkout commit "' . $commit . '"');
            $this->runCommand('git checkout ' . $commit, $buildDir);
        }

        $replace = $this->getCommandReplacements($project, $buildDir);
        foreach ($config->getBuildCommands() as $cmd) {
            $this->runCommand($cmd, $buildDir, $replace);
        }

        $this->notify('build done! (' . basename($buildDir) . ')');
    }

    /**
     * @param string $project
     * @param string $env
     *
     * @return void
     */
    private function deploy($project, $env)
    {
        $config = $this->loadConfig($project);

        if (!$env) {
            throw new \Exception('no environment given');
        }

        $buildDir = $this->getBuildDir($project);
        if (!$buildDir) {
            throw new \Exception('no build found. Please run build command first!');
        }

        if (preg_match('/^(.*-)([0-9]+)$/', $buildDir, $matches)) {
            $buildNumber = (int)$matches[2];
            $buildDirPrefix = $matches[1];
        } else {
            throw new \Exception('invalid build dir');
        }

        $input = (int)$this->input->get('Enter build number to deploy [' . $buildNumber . ']: ');
        if ($input) {
            $buildNumber = $input;
        }

        $srcDir = $buildDirPrefix . $buildNumber;
        if (!is_dir($srcDir)) {
            throw new \Exception('no build found for number ' . $buildNumber);
        }

        $commands = $config->getDeployCommands($env);
        if (!$commands) {
            throw new \Exception('no deployment commands configured for environment "' . $env . '"');
        }

        $replace = $this->getCommandReplacements($project, $buildDir);
        foreach ($commands as $cmd) {
            $this->runCommand($cmd, $srcDir, $replace);
        }

        $this->notify('deployment done!');
    }
}

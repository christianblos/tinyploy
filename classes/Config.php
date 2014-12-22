<?php
namespace Tinyploy;

/**
 * @author    Christian Blos <christian.blos@gmx.de>
 * @copyright Copyright (c) 2014-2015, Christian Blos
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @link      https://github.com/christianblos
 *
 * @property string $repo
 */
class Config
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getBuildCommands()
    {
        $commands = $this->__get('buildCommands');
        if (is_array($commands)) {
            return $commands;
        }
        return [];
    }

    /**
     * @param string $env
     *
     * @return array
     */
    public function getDeployCommands($env)
    {
        $commands = $this->__get('deployCommands');
        if (isset($commands[$env]) && is_array($commands[$env])) {
            return $commands[$env];
        }
        return [];
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return null;
    }
}

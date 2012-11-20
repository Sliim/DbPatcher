<?php
/**
 * This source file is part of DbPatcher.
 *
 * DbPatcher is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * DbPatcher is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with DbPatcher. If not, see <http://www.gnu.org/licenses/gpl-3.0.html>.
 *
 * PHP version 5.3
 *
 * @category DbPatcher
 * @package  Commander
 * @author   Sliim <sliim@mailoo.org>
 * @license  GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @link     http://www.sliim-projects.eu
 */

namespace DbPatcher;

/**
 * Command manager
 *
 * @category DbPatcher
 * @package  Commander
 */
class Commander
{

    /** @var int */
    const EXIT_CODE = 1337;

    /**
     * Commands list
     * @var array
     */
    private $commands = array();


    /**
     * Constructor
     *
     * @throws \RuntimeException if sapi isn't cli
     *
     * @return void
     */
    public function __construct()
    {
        if (php_sapi_name() !== 'cli') {
            throw new \RuntimeException('Class usable in CLI only !');
        }
    }

    /**
     * Magic call
     * Execute a command if exists
     *
     * @param string $command Command to execute
     * @param array  $args    Command's arguments
     *
     * @throws \BadMethodCallException When command not found
     *
     * @return mixed Command's result
     */
    final public function __call($command, array $args)
    {
        $method = 'c' . ucfirst($command);
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }

        if (array_key_exists($command, $this->commands)) {
            return call_user_func_array($this->commands[$command]['command'], $args);
        }

        throw new \BadMethodCallException('Command `' . $command . '` not found.');
    }

    /**
     * Add a command
     *
     * @param string $name        Command's name
     * @param mixed  $command     String for a function name or array(object => method)
     * @param string $description Command's description
     *
     * @throws \BadMethodCallException
     * @throws \BadFunctionCallException
     *
     * @return Commander
     */
    public function addCommand($name, $command, $description = '')
    {
        if (is_array($command)) {
            if (!method_exists($command[0], $command[1])) {
                throw new \BadMethodCallException(
                    'Method `' . $method . '` not found in ' . get_class($object)
                );
            }
        } else {
            if (!function_exists($command)) {
                throw new \BadFunctionCallException('Function `' . $command . '` doesn\'t exists');
            }
        }

        $this->commands[$name] = array(
            'command'     => $command,
            'description' => $description,
        );
        return $this;
    }

    /**
     * Help command
     *
     * @todo Get possible arguments for each command
     *
     * @return string
     */
    public function cHelp()
    {
        $help  = 'Commands:' . PHP_EOL;
        $help .= '--------' . PHP_EOL;
        foreach ($this->commands as $name => $command) {
            $help .= $name . "\t=> " . $command['description'] . PHP_EOL;
        }

        $help .= PHP_EOL . '------' . PHP_EOL;
        $help .= "help\t=> Show this help" . PHP_EOL;
        $help .= "exit\t=> Exit script" . PHP_EOL;

        return $help;
    }

    /**
     * Exit command
     *
     * @throws \Exception with EXIT_CODE constant
     *
     * @return void
     */
    public function cExit()
    {
        throw new \Exception('exit', static::EXIT_CODE);
    }
}

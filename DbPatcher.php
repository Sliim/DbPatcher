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
 * @package  DbPatcher
 * @author   Sliim <sliim@mailoo.org>
 * @license  GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @link     http://www.sliim-projects.eu
 */

require_once 'library/DbPatcher/Commander.php';
require_once 'library/DbPatcher/Prompt.php';
require_once 'library/DbPatcher/Patch.php';
require_once 'library/DbPatcher/Patcher.php';

use DbPatcher\Patcher;
use DbPatcher\Commander;
use DbPatcher\Prompt;

/**
 * DbPatcher
 *
 * @category DbPatcher
 * @package  DbPatcher
 */
class DbPatcher
{

    /**
     * @var PDO
     */
    private $connection = null;

    /**
     * @var DbPatcher\Patcher
     */
    private $patcher = null;

    /**
     * @var DbPatcher\Prompt
     */
    private $prompt = null;

    /**
     * @var DbPatcher\Commander
     */
    private $commander = null;

    /**
     * DbPatcher contructor
     *
     * @param array  $dbConfig Database configuration
     * @param string $patchDir Patch directory
     *
     * @throws RuntimeException
     *
     * @return void
     */
    public function __construct(array $dbConfig, $patchDir)
    {
        $diff = array_diff(
            array('driver', 'hostname', 'username', 'password'),
            array_keys($dbConfig)
        );

        if (!empty($diff)) {
            throw new RuntimeException(
                'Missing (' . implode(', ', $diff) . ') in database configuration'
            );
        }

        if (!file_exists($patchDir)) {
            throw new RuntimeException('Patch directory does not exists!');
        }

        $this->connection = new PDO(
            $dbConfig['driver'] . ':host=' .
            $dbConfig['hostname'] . ';dbname=' .
            $dbConfig['dbname'],
            $dbConfig['username'],
            $dbConfig['password']
        );

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->patchDir = $patchDir;
    }

    /**
     * Run DbPatcher
     *
     * @return void
     */
    public function run()
    {
        $this->getPrompt()->run();
    }

    /**
     * Patcher getter
     *
     * @return Patcher
     */
    public function getPatcher()
    {
        if (is_null($this->patcher)) {
            $this->patcher = new Patcher($this->patchDir, $this->connection);
        }

        return $this->patcher;
    }

    /**
     * Commander getter
     *
     * @return Commander
     */
    public function getCommander()
    {
        if (is_null($this->commander)) {
            $this->commander = new Commander();
            $this->commander
                ->addCommand('quit', array($this->commander, 'cExit'), 'Alias of `exit`')
                ->addCommand('list', array($this, 'getList'), 'Patch list')
                ->addCommand('status', array($this, 'status'), 'Database status')
                ->addCommand('up', array($this->getPatcher(), 'upgrade'), 'Upgrade database')
                ->addCommand('down', array($this->getPatcher(), 'downgrade'), 'Downgrade database');
        }

        return $this->commander;
    }

    /**
     * Prompt getter
     *
     * @return Prompt
     */
    public function getPrompt()
    {
        if (is_null($this->prompt)) {
            $this->prompt = new Prompt($this->getCommander());
            $this->prompt->setPrompt('dbpatcher>>>');
        }

        return $this->prompt;
    }

    /**
     * Patcher setter
     *
     * @param Patcher $patcher Patcher to set
     *
     * @return DbPatcher
     */
    public function setPatcher(Patcher $patcher)
    {
        $this->patcher = $patcher;
        return $this;
    }

    /**
     * Commander setter
     *
     * @param Commander $commander Commander to set
     *
     * @return DbPatcher
     */
    public function setCommander(Commander $commander)
    {
        $this->commander = $commander;
        return $this;
    }

    /**
     * Prompt setter
     *
     * @param Prompt $prompt Prompt to set
     *
     * @return DbPatcher
     */
    public function setPrompt(Prompt $prompt)
    {
        $this->prompt = $prompt;
        return $this;
    }

    /**
     * Print database status
     *
     * @return void
     */
    public function status()
    {
        $status = $this->getPatcher()->status();

        echo 'Database status: version ' . $status['status'] . PHP_EOL;

        if (empty($status['toInstall'])) {
            echo 'Database up-to-date!' . PHP_EOL;
        } else {
            echo 'Patch to install:' . PHP_EOL;
            foreach ($status['toInstall'] as $patch) {
                echo "\t-$patch\r\n";
            }
        }

        echo PHP_EOL;
    }

    /**
     * Print patch list
     *
     * @return void
     */
    public function getList()
    {
        $list = $this->getPatcher()->getList();

        echo 'Patch list:' . PHP_EOL;
        foreach ($list as $patch) {
            echo "\t-$patch\r\n";
        }

        echo PHP_EOL;
    }
}

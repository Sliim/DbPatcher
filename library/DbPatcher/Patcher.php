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
 * @package  Patcher
 * @author   Sliim <sliim@mailoo.org>
 * @license  GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @link     http://www.sliim-projects.eu
 */

namespace DbPatcher;

/**
 * Patch manager
 *
 * @category DbPatcher
 * @package  Patcher
 */
class Patcher
{
    /**
     * @var PDO
     */
    private $connection;

    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $state = array();

    /**
     * @var string
     */
    private $stateFile;

    /**
     * Constructor, set patch path
     *
     * @param string $path Path to patch
     * @param PDO    $con  Database connection
     *
     * @return void
     */
    public function __construct($path, \PDO $con)
    {
        $this->path = $path;
        $this->connection  = $con;

        $this->stateFile = $path . DIRECTORY_SEPARATOR . 'patcher_state.txt';
        if (file_exists($this->stateFile)) {
            $this->loadState();
        } else {
            touch($this->stateFile);
        }
    }

    /**
     * Return array as patch list
     * Key => patch's name
     * Value => boolean, Installed or not
     *
     * @return array
     */
    public function getList()
    {
        $patches = array();

        $iterator = new \DirectoryIterator($this->path);
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/^patch[0-9]{4}\.php$/', $file->getFilename())) {
                array_push($patches, str_replace('.php', '', $file->getFilename()));
            }
        }

        return $patches;
    }

    /**
     * Get database status
     *
     * @return array
     */
    public function status()
    {
        $status = array(
                   'status'    => 0,
                   'toInstall' => array(),
                  );

        $state = $this->state;
        if (!empty($state)) {
            $status['status'] = $this->getVersion(array_pop($state));
        }

        foreach ($this->getList() as $patch) {
            if (!$this->isInstalled($patch)) {
                array_push($status['toInstall'], $patch);
            }
        }

        return $status;
    }

    /**
     * Database upgrading
     *
     * @param int $version Target version, if NULL upgrade all patch
     *
     * @return bool
     */
    public function upgrade($version=NULL)
    {
        if (is_null($version)) {
            $patchTarget = array_pop($this->getList());
            $version     = $this->getVersion($patchTarget);
        } else {
            $patchTarget = $this->getNameFromVersion($version);
        }

        foreach ($this->getList() as $patchName) {
            if ($this->isInstalled($patchName)) {
                continue;
            }

            $patch = $this->loadPatch($patchName);
            $patch->upgrade();
            $patch->execute();

            array_push($this->state, $patchName);

            if ($patchName === $patchTarget) {
                break;
            }
        }

        $this->refreshStateFile();
    }

    /**
     * Database downgrading
     *
     * @param int $version Target version, if NULL downgrade all patch
     *
     * @return bool
     */
    public function downgrade($version=NULL)
    {
        if (is_null($version)) {
            $version = 0;
        }

        $patchTarget = $this->getNameFromVersion($version);
        $list        = array_reverse($this->getList());

        foreach ($list as $patchName) {
            if ($patchName === $patchTarget) {
                break;
            }

            if (!$this->isInstalled($patchName)) {
                continue;
            }

            $patch = $this->loadPatch($patchName);
            $patch->downgrade();
            $patch->execute();

            $stateKey = array_search($patchName, $this->state);
            unset($this->state[$stateKey]);
        }

        $this->refreshStateFile();
    }

    /**
     * Check if patch is already installed
     *
     * @param string $patch Patch to check
     *
     * @return bool
     */
    public function isInstalled($patch)
    {
        $this->checkPatchName($patch);
        return in_array($patch, $this->state);
    }

    /**
     * Load patcher state
     *
     * @return void
     */
    private function loadState()
    {
        $this->state = array();
        $state        = file($this->stateFile);

        foreach ($state as $patch) {
            $patch = trim($patch);

            if (empty($patch)) {
                continue;
            }

            $this->checkPatchName($patch);

            if ($this->patchExists($patch)) {
                array_push($this->state, $patch);
            }
        }
    }

    /**
     * Refresh state file
     *
     * @return bool
     */
    private function refreshStateFile()
    {
        $data = implode(PHP_EOL, $this->state);
        if (!file_put_contents($this->stateFile, $data)) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Check if patch exists
     *
     * @param string $patch Patch to check
     *
     * @return bool
     */
    private function patchExists($patch)
    {
        if (file_exists($this->getPatchPath($patch))) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Get patch's version
     *
     * @param string $patch Patch
     *
     * @return int
     */
    private function getVersion($patch)
    {
        $this->checkPatchName($patch);
        return (int) preg_replace('/patch[0]*/', '', $patch);
    }

    /**
     * Check is a valid patch name
     *
     * @param string $patch Patch to check
     *
     * @throws UnexpectedValueException if name is invalid
     *
     * @return void
     */
    private function checkPatchName($patch)
    {
        if (!preg_match('/^patch[0-9]{4}$/', $patch)) {
            throw new \UnexpectedValueException('Patch name invalid (' . $patch . ')');
        }
    }

    /**
     * Get patch name from version
     *
     * @param int $version Version wanted
     *
     * @throws LengthException If version too long
     *
     * @return string
     */
    private function getNameFromVersion($version)
    {
        $name  = 'patch';
        $count = strlen((string) $version);

        if ($count > 4) {
            throw new \LengthException('Version number accept only 4 characters');
        }

        $countZero = 4 - $count;

        for ($i = 0; $i < $countZero; $i++) {
            $name .= '0';
        }

        return $name . $version;
    }

    /**
     * Get patch's path
     *
     * @param string $patch Patch to get
     *
     * @return string
     */
    private function getPatchPath($patch)
    {
        return $this->path . DIRECTORY_SEPARATOR . $patch . '.php';
    }

    /**
     * Load a patch
     *
     * @param string $patch Patch to get
     *
     * @throws InvalidArgumentException if patch not found
     *
     * @return Patch
     */
    private function loadPatch($patch)
    {
        $this->checkPatchName($patch);

        if (!$this->patchExists($patch)) {
            throw new \InvalidArgumentException('Patch not found');
        }

        $patchFile  = $this->getPatchPath($patch);
        $patchClass = ucfirst($patch);

        if (!class_exists($patchClass)) {
            include $patchFile;
        }

        return new $patchClass($this->connection);
    }
}

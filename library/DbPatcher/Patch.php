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
 * @package  Patch
 * @author   Sliim <sliim@mailoo.org>
 * @license  GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @link     http://www.sliim-projects.eu
 */

namespace DbPatcher;

/**
 * Abstract Patch
 *
 * @category DbPatcher
 * @package  Patch
 */
abstract class Patch
{

    /**
     * @var PDO
     */
    private $connection;

    /**
     * @var array
     */
    private $queries = array();

    /**
     * Constructor, set connection
     *
     * @param PDO $con Connexion
     *
     * @return void
     */
    public function __construct(PDO $con)
    {
        $this->connection = $con;
    }

    /**
     * Upgrade method
     *
     * @return void
     */
    abstract public function upgrade();

    /**
     * Downgrade method
     *
     * @return void
     */
    abstract public function downgrade();

    /**
     * Prepare a query
     *
     * @param string $sql Query to prepare
     *
     * @return void
     */
    final protected function prepare($sql)
    {
        echo '[INFO] Prepare query: ' . $sql . PHP_EOL;
        $this->queries[] = $this->connection->prepare($sql);
    }

    /**
     * Execute all prepared queries
     *
     * @throws RuntimeException
     *
     * @return void
     */
    final public function execute()
    {
        $this->connection->beginTransaction();
        foreach ($this->queries as $query) {
            echo '[INFO] Execute query: ' . $query->queryString . PHP_EOL;
            try {
                $query->execute();
            } catch (\PDOException $e) {
                $this->connection->rollBack();
                throw new \RuntimeException(
                    'Error on query `' . $query->queryString .
                    '`.. Process to Rollback!' . PHP_EOL . 'Reason: ' .
                    $e->getMessage()
                );
            }
        }

        $this->connection->commit();
    }
}

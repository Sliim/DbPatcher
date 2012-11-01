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
 * @package  Prompt
 * @author   Sliim <sliim@mailoo.org>
 * @license  GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @link     http://www.sliim-projects.eu
 */

namespace DbPatcher;

/**
 * Prompt class
 *
 * @category DbPatcher
 * @package  Prompt
 *
 * @todo implement history
 */
class Prompt
{

    /**
     * @var Commander
     */
    private $commander = null;

    /**
     * @var string
     */
    private $prompt = '=>>';

    /**
     * Constructor
     * Set commander
     *
     * @param Commander $commander Commander object
     *
     * @return void
     */
    public function __construct(Commander $commander)
    {
        $this->commander = $commander;
    }

    /**
     * Set prompt string
     *
     * @param string $prompt Prompt to set
     *
     * @return void
     */
    public function setPrompt($prompt)
    {
        $this->prompt = trim($prompt);
    }

    /**
     * Run script, read STDIN on infinite loop
     *
     * @return void
     */
    public function run()
    {
        $c = '';
        do {
            try {
                $c = trim($c);
                if (!empty($c)) {
                    $args   = explode(' ', $c);
                    $c      = array_shift($args);
                    $result = call_user_func_array(array($this->commander, $c), $args);

                    switch (gettype($result)) {
                        case 'string':
                        case 'integer':
                        case 'double':
                            echo $result . PHP_EOL;
                            break;
                        case 'NULL':
                            break;
                        default:
                            var_dump($result);
                            break;
                    }
                }
            } catch (\Exception $e) {
                if ($e->getCode() === Commander::EXIT_CODE) {
                    echo 'exiting' . PHP_EOL;
                    return true;
                } else {
                    echo "\033[31m[" . get_class($e) . "]\033[0m " . $e->getMessage() . PHP_EOL;
                    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
                }
            }
            echo $this->prompt . ' ';
        } while ($c = fgets(STDIN));
    }
}

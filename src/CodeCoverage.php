<?php
/**
 * PHP_CodeCoverage
 *
 * Copyright (c) 2009-2014, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright  2009-2014 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      File available since Release 1.0.0
 */

use SebastianBergmann\Environment\Runtime;

/**
 * Provides collection functionality for PHP code coverage information.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright  2009-2014 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      Class available since Release 1.0.0
 */
class PHP_CodeCoverage
{
    /**
     * @var PHP_CodeCoverage_Driver
     */
    private $driver;

    /**
     * @var PHP_CodeCoverage_Filter
     */
    private $filter;

    /**
     * @var PHP_CodeCoverage_Parser
     */
    private $parser;

    /**
     * @var boolean
     */
    private $cacheTokens = false;

    /**
     * @var boolean
     */
    private $checkForUnintentionallyCoveredCode = false;

    /**
     * @var boolean
     */
    private $forceCoversAnnotation = false;

    /**
     * @var boolean
     */
    private $mapTestClassNameToCoveredClassName = false;

    /**
     * @var boolean
     */
    private $addUncoveredFilesFromWhitelist = true;

    /**
     * @var boolean
     */
    private $processUncoveredFilesFromWhitelist = false;

    /**
     * @var mixed
     */
    private $currentId;

    /**
     * Code coverage data.
     *
     * @var array
     */
    private $data = array();

    /**
     * Test data.
     *
     * @var array
     */
    private $tests = array();

    /**
     * Constructor.
     *
     * @param  PHP_CodeCoverage_Driver $driver
     * @param  PHP_CodeCoverage_Filter $filter
     * @throws PHP_CodeCoverage_Exception
     */
    public function __construct(PHP_CodeCoverage_Driver $driver = null, PHP_CodeCoverage_Filter $filter = null)
    {
        if ($filter === null) {
            $filter = new PHP_CodeCoverage_Filter;
        }

        $parser = new PHP_CodeCoverage_Parser;

        if ($driver === null) {
            $runtime = new Runtime;

            if ($runtime->isHHVM()) {
                $driver = new PHP_CodeCoverage_Driver_HHVM($filter, $parser);
            } elseif ($runtime->hasXdebug()) {
                $driver = new PHP_CodeCoverage_Driver_Xdebug($filter, $parser);
            } else {
                throw new PHP_CodeCoverage_Exception('No code coverage driver available');
            }
        }

        $this->driver = $driver;
        $this->filter = $filter;
        $this->parser = $parser;
    }

    /**
     * Returns the PHP_CodeCoverage_Report_Node_* object graph
     * for this PHP_CodeCoverage object.
     *
     * @return PHP_CodeCoverage_Report_Node_Directory
     * @since  Method available since Release 1.1.0
     */
    public function getReport()
    {
        $factory = new PHP_CodeCoverage_Report_Factory;

        return $factory->create($this);
    }

    /**
     * Clears collected code coverage data.
     */
    public function clear()
    {
        $this->currentId = null;
        $this->data      = array();
        $this->tests     = array();
    }

    /**
     * Returns the PHP_CodeCoverage_Filter used.
     *
     * @return PHP_CodeCoverage_Filter
     */
    public function filter()
    {
        return $this->filter;
    }

    /**
     * Returns the collected code coverage data.
     * Set $raw = true to bypass all filters.
     *
     * @param bool $raw
     * @return array
     * @since  Method available since Release 1.1.0
     */
    public function getData($raw = false)
    {
        if (!$raw && $this->addUncoveredFilesFromWhitelist) {
            $this->addUncoveredFilesFromWhitelist();
        }

        return $this->data;
    }

    /**
     * Sets the coverage data.
     *
     * @param array $data
     * @since Method available since Release 2.0.0
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Returns the test data.
     *
     * @return array
     * @since  Method available since Release 1.1.0
     */
    public function getTests()
    {
        return $this->tests;
    }

    /**
     * Sets the test data.
     *
     * @param array $tests
     * @since Method available since Release 2.0.0
     */
    public function setTests(array $tests)
    {
        $this->tests = $tests;
    }

    /**
     * Start collection of code coverage information.
     *
     * @param  mixed                      $id
     * @param  boolean                    $clear
     * @throws PHP_CodeCoverage_Exception
     */
    public function start($id, $clear = false)
    {
        if (!is_bool($clear)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        if ($clear) {
            $this->clear();
        }

        $this->currentId = $id;

        $this->driver->start();
    }

    /**
     * Stop collection of code coverage information.
     *
     * @param  boolean                    $append
     * @param  mixed                      $linesToBeCovered
     * @param  array                      $linesToBeUsed
     * @return array
     * @throws PHP_CodeCoverage_Exception
     */
    public function stop($append = true, $linesToBeCovered = array(), array $linesToBeUsed = array())
    {
        if (!is_bool($append)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        if (!is_array($linesToBeCovered) && $linesToBeCovered !== false) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                2,
                'array or false'
            );
        }

        $data = $this->driver->stop();
        $this->append($data, null, $append, $linesToBeCovered, $linesToBeUsed);

        $this->currentId = null;

        return $data;
    }

    /**
     * Appends code coverage data.
     *
     * @param  array                      $data
     * @param  mixed                      $id
     * @param  boolean                    $append
     * @param  mixed                      $linesToBeCovered
     * @param  array                      $linesToBeUsed
     * @throws PHP_CodeCoverage_Exception
     */
    public function append(array $data, $id = null, $append = true, $linesToBeCovered = array(), array $linesToBeUsed = array())
    {
        if ($id === null) {
            $id = $this->currentId;
        }

        if ($id === null) {
            throw new PHP_CodeCoverage_Exception;
        }

        $this->initializeFilesThatAreSeenTheFirstTime($data);

        if (!$append) {
            return;
        }

        if ($id != 'UNCOVERED_FILES_FROM_WHITELIST') {
            $this->applyCoversAnnotationFilter(
                $data,
                $linesToBeCovered,
                $linesToBeUsed
            );
        }

        if (empty($data)) {
            return;
        }

        $status = null;

        if ($id instanceof PHPUnit_Framework_TestCase) {
            $status = $id->getStatus();
            $id     = get_class($id) . '::' . $id->getName();
        } elseif ($id instanceof PHPUnit_Extensions_PhptTestCase) {
            $id = $id->getName();
        }

        $this->tests[$id] = $status;

        foreach ($data as $file => $lines) {
            if (!$this->filter->isFile($file)) {
                continue;
            }

            foreach ($lines as $k => $v) {
                if ($v == 1) {
                    $this->data[$file][$k][] = $id;
                }
            }
        }
    }

    /**
     * Merges the data from another instance of PHP_CodeCoverage.
     *
     * @param PHP_CodeCoverage $that
     */
    public function merge(PHP_CodeCoverage $that)
    {
        foreach ($that->data as $file => $lines) {
            if (!isset($this->data[$file])) {
                if (!$this->filter->isFiltered($file)) {
                    $this->data[$file] = $lines;
                }

                continue;
            }

            foreach ($lines as $line => $data) {
                if ($data !== null) {
                    if (!isset($this->data[$file][$line])) {
                        $this->data[$file][$line] = $data;
                    } else {
                        $this->data[$file][$line] = array_unique(
                            array_merge($this->data[$file][$line], $data)
                        );
                    }
                }
            }
        }

        $this->tests = array_merge($this->tests, $that->getTests());
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     * @since  Method available since Release 1.1.0
     */
    public function setCacheTokens($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->parser->setCacheTokens($flag);

        $this->cacheTokens = $flag;
    }

    /**
     * @since Method available since Release 1.1.0
     */
    public function getCacheTokens()
    {
        return $this->cacheTokens;
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     * @since  Method available since Release 2.0.0
     */
    public function setCheckForUnintentionallyCoveredCode($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->checkForUnintentionallyCoveredCode = $flag;
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     */
    public function setForceCoversAnnotation($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->forceCoversAnnotation = $flag;
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     */
    public function setMapTestClassNameToCoveredClassName($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->mapTestClassNameToCoveredClassName = $flag;
    }

    /**
     * @return bool
     */
    public function getMapTestClassNameToCoveredClassName()
    {
        return $this->mapTestClassNameToCoveredClassName;
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     */
    public function setAddUncoveredFilesFromWhitelist($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->addUncoveredFilesFromWhitelist = $flag;
    }

    /**
     * @param  boolean                    $flag
     * @throws PHP_CodeCoverage_Exception
     */
    public function setProcessUncoveredFilesFromWhitelist($flag)
    {
        if (!is_bool($flag)) {
            throw PHP_CodeCoverage_Util_InvalidArgumentHelper::factory(
                1,
                'boolean'
            );
        }

        $this->processUncoveredFilesFromWhitelist = $flag;
    }

    /**
     * Applies the @covers annotation filtering.
     *
     * @param  array                                                 $data
     * @param  mixed                                                 $linesToBeCovered
     * @param  array                                                 $linesToBeUsed
     * @throws PHP_CodeCoverage_Exception_UnintentionallyCoveredCode
     */
    private function applyCoversAnnotationFilter(array &$data, $linesToBeCovered, array $linesToBeUsed)
    {
        if ($linesToBeCovered === false ||
            ($this->forceCoversAnnotation && empty($linesToBeCovered))) {
            $data = array();

            return;
        }

        if (empty($linesToBeCovered)) {
            return;
        }

        if ($this->checkForUnintentionallyCoveredCode) {
            $this->performUnintentionallyCoveredCodeCheck(
                $data,
                $linesToBeCovered,
                $linesToBeUsed
            );
        }

        $data = array_intersect_key($data, $linesToBeCovered);

        foreach (array_keys($data) as $filename) {
            $_linesToBeCovered = array_flip($linesToBeCovered[$filename]);

            $data[$filename] = array_intersect_key(
                $data[$filename],
                $_linesToBeCovered
            );
        }
    }

    /**
     * @param array $data
     * @since Method available since Release 1.1.0
     */
    private function initializeFilesThatAreSeenTheFirstTime(array $data)
    {
        foreach ($data as $file => $lines) {
            if ($this->filter->isFile($file) && !isset($this->data[$file])) {
                $this->data[$file] = array();

                foreach ($lines as $k => $v) {
                    $this->data[$file][$k] = $v == -2 ? null : array();
                }
            }
        }
    }

    /**
     * Processes whitelisted files that are not covered.
     */
    private function addUncoveredFilesFromWhitelist()
    {
        $data           = array();
        $uncoveredFiles = array_diff(
            $this->filter->getWhitelist(),
            array_keys($this->data)
        );

        foreach ($uncoveredFiles as $uncoveredFile) {
            if (!file_exists($uncoveredFile)) {
                continue;
            }

            if ($this->processUncoveredFilesFromWhitelist) {
                $this->processUncoveredFileFromWhitelist(
                    $uncoveredFile,
                    $data,
                    $uncoveredFiles
                );
            } else {
                $data[$uncoveredFile] = array();

                $lines = count(file($uncoveredFile));

                for ($i = 1; $i <= $lines; $i++) {
                    $data[$uncoveredFile][$i] = -1;
                }
            }
        }

        $this->append($data, 'UNCOVERED_FILES_FROM_WHITELIST');
    }

    /**
     * @param string $uncoveredFile
     * @param array  $data
     * @param array  $uncoveredFiles
     */
    private function processUncoveredFileFromWhitelist($uncoveredFile, array &$data, array $uncoveredFiles)
    {
        $this->driver->start();
        include_once $uncoveredFile;
        $coverage = $this->driver->stop();

        foreach ($coverage as $file => $fileCoverage) {
            if (!isset($data[$file]) &&
                in_array($file, $uncoveredFiles)) {
                foreach (array_keys($fileCoverage) as $key) {
                    if ($fileCoverage[$key] == 1) {
                        $fileCoverage[$key] = -1;
                    }
                }

                $data[$file] = $fileCoverage;
            }
        }
    }

    /**
     * @param  array                                                 $data
     * @param  array                                                 $linesToBeCovered
     * @param  array                                                 $linesToBeUsed
     * @throws PHP_CodeCoverage_Exception_UnintentionallyCoveredCode
     * @since Method available since Release 2.0.0
     */
    private function performUnintentionallyCoveredCodeCheck(array &$data, array $linesToBeCovered, array $linesToBeUsed)
    {
        $allowedLines = $this->parser->getAllowedLines(
            $linesToBeCovered,
            $linesToBeUsed
        );

        $message = '';

        foreach ($data as $file => $_data) {
            foreach ($_data as $line => $flag) {
                if ($flag == 1 &&
                    (!isset($allowedLines[$file]) ||
                        !isset($allowedLines[$file][$line]))) {
                    $message .= sprintf(
                        '- %s:%d' . PHP_EOL,
                        $file,
                        $line
                    );
                }
            }
        }

        if (!empty($message)) {
            throw new PHP_CodeCoverage_Exception_UnintentionallyCoveredCode(
                $message
            );
        }
    }
}

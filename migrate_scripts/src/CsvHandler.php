<?php
namespace SSHOC\Conversion;

/**
 * @Author: Klaus Illmayer, klaus.illmayer@oeaw.ac.at
 * 
 * MIT License
 * 
 * Copyright (c) 2021 OEAW ACDH-CH, Klaus Illmayer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

/**
 * Handling of CSV files.
 */

// Relying on external library for CSV reading (install with composer)
use League\Csv\Reader;

class CsvHandler
{
  private $configClass = null;

  private $csvFile = '';
  private $hasHeader = false;
  
  private $csvRecords = [];

  public function __construct($configClass, $csvFile, $hasHeader)
  {
    $this->configClass = $configClass;

    if (!file_exists($csvFile)) {
      Logger::log('Import file $csvFile is missing. '
          . 'Not possible to run the script without import data.',
          __CLASS__.'.'.__FUNCTION__, true);
    }
    $this->csvFile = $csvFile;

    $this->hasHeader = $hasHeader;
  }
  
  /**
   * Load the data from the csvFile defined in the constructor (csvFile).
   * 
   * @param bool $hasHeader
   * @return array
   */
  public function loadCsvData()
  {
    $csv = Reader::createFromPath($this->csvFile, 'r');
    if($this->hasHeader) {
      $csv->setHeaderOffset(0);
    }
    $this->csvRecords = $csv->getRecords();
    if (empty($this->csvRecords)) {
      Logger::log('Import file '. $this->csvFile . ' does not have any data. '
          . 'Not possible to run the script without import data.',
          __CLASS__.'.'.__FUNCTION__, true);
    }
  }

  /**
   * Process the csv records to get them in a readable version.
   * 
   */
  public function processCsv() {
    $importData = [];
    // get the current csvStructure
    $csvStructure = $this->configClass->getConfig('csv_structure');
    $csvProcessFromRow = $this->configClass->getConfig('csv_process_from_row');

    foreach($this->csvRecords as $row=>$record) {
      if ($row >= $csvProcessFromRow) {
        if (isset($csvStructure[$row])) {
          foreach($record as $col=>$value) {
            if ($col
                >= $this->configClass->getConfig(
                    'csv_process_until_column', false, 42)) {
              break;
            }
            // the first columns are to ignore, because this is the header
            if (!in_array($col, $csvIgnoreCols)) {
              $value = trim($value);
              // integrate the column in the json (what we do: switch from left header to upper header)
              // if there is a ' ; ' in the value, then it is multivalue - create an array
              if (strpos($value, ' ; ') !== FALSE) {
                $values = explode(' ; ', $value);
                foreach($values as $val) {
                  $importData[$col][$csvStructure[$row]][] = trim($val);
                }
              }
              elseif (strtoupper($value) == strtoupper('N/A')) {
                // Ignore N/A values, but don't unset it, set an empty value
                $importData[$col][$csvStructure[$row]] = '';
              }
              else {
                $importData[$col][$csvStructure[$row]] = trim($value);
              }
          }
        }
      }
    }
    return $importData;
  }

}
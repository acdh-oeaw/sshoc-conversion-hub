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
  private $csvFile = '';
  private $hasHeader = false;
  private $structure = [];
  private $processFromRow = 0;
  
  private $csvRecords = [];

  public function __construct($csvFile, $hasHeader, $structure, $processFromRow)
  {
    if (!file_exists($csvFile)) {
      Logger::log("Import file $csvFile is missing. "
          . 'Not possible to run the script without import data.',
          __CLASS__.'.'.__FUNCTION__, true);
    }
    $this->csvFile = $csvFile;
    $this->hasHeader = $hasHeader;
    $this->structure = $structure;
    $this->processFromRow = $processFromRow;
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
   * This process method is column oriented (values are in the columns).
   */
  public function processCsv()
  {
    $importData = [];

    foreach($this->csvRecords as $row=>$record) {
      // Ignore rows due to general information not relevant to process.
      if ($row >= $this->processFromRow) {
        // Go through the structure and gather the data.
        $dataComplete = true;
        $data = [];
        foreach($this->structure as $col=>$colTitle) {
          // Check if data is set, otherwise ignore.
          // Title needs to be valid, otherwise give an error.
          // @todo: define in configuration what values are necessary.
          if (isset($record[$col])) {
            if (empty($record[$col]) && $colTitle == 'title') {
              /*Logger::log('Import file '. $this->csvFile
                  . " does have not filled data. Row: $row",
                  __CLASS__.'.'.__FUNCTION__);*/
              $dataComplete = false;
            } else {
              $value = trim($record[$col]);
              // integrate the column in the json (what we do: switch from left header to upper header)
              // if there is a ' ; ' in the value, then it is multivalue - create an array
              if (strpos($value, ' ; ') !== FALSE) {
                $values = explode(' ; ', $value);
                foreach($values as $val) {
                  $data[$colTitle][] = trim($val);
                }
              } else {
                $data[$colTitle][] = $value;
              }
            }
          } else {
            Logger::log('Import file '. $this->csvFile
              . " does have not set data, row: $row",
              __CLASS__.'.'.__FUNCTION__, true);
            $dataComplete = false;
          }
        }
        if ($dataComplete) {
          $importData[] = $data;
        }
      }
    }
    return $importData;
  }

}
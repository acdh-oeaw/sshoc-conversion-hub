<?php
// src/Util/CsvHandler.php

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
namespace App\Util;

use Psr\Log\LoggerInterface;
// Relying on external library for CSV reading (install with composer)
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Handling of CSV files.
 */
class CsvHandler
{
  private $logger;

  private $csvFile = '';
  private $hasHeader = false;
  private $processFromRow = 0;
  private $multivalSeparator = ' ; ';
  private $structure = [];
  
  private $csvRecords = [];
  
  public function __construct($csvImportFile, $csvImportFileHasHeader,
      $csvImportFileProcessFromRow, $csvImportFileMultivalSeparator,
      $csvImportFileStructure,
      LoggerInterface $logger)
  {
    $this->logger = $logger;
    $this->csvFile = $csvImportFile;
    if (!file_exists($this->csvFile)) {
      $this->logger->critical("Import file $this->csvFile is missing.");
    }

    $this->hasHeader = $csvImportFileHasHeader;
    $this->processFromRow = $csvImportFileProcessFromRow;
    $this->multivalSeparator = $csvImportFileMultivalSeparator;
    $this->structure = $csvImportFileStructure;
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
      $this->logger->critical('Import file '. $this->csvFile
          . ' does not have any data.');
    }
  }
  
  function handleArrayCsv($convArray) {
    $conv = '';
    if (!empty($convArray)) {
      if (is_array($convArray)) {
        $conv = implode('|', $convArray); // assuming that | is not used in any of the terms
      }
    }
    return $conv;
  }

  function writeCSVData(array $importData, string $csvFileName) {
    $writer = Writer::createFromPath($csvFileName, 'w+');
    $writeData = [];
    foreach($importData as $row=>$data) {
      $line = [];
      foreach($data as $tmp=>$column) {
        if (is_array($column)) {
          $line[] = $this->handleArrayCsv($column);
        } else {
          $line[] = $column;
        }
      }
      if (!empty($line)) {
        $writeData[] = $line;
      }
    }
    $writer->insertAll($writeData);
  }

  /**
   * Processes the value from a csv.
   * Most important: look for multivalues separated (defined in config for wp3)
   * @param string $valueCsv
   * @return array
   */
  private function getValueFromCsv(string $valueCsv):array
  {
    $processedVal = [];
    $value = trim($valueCsv);
    // Integrate the column in the json
    // (what we do: switch from left header to upper header)
    // if there is a separator in the value, then it is multivalue
    // - create an array
    if (strpos($value, $this->multivalSeparator) !== FALSE) {
      $multiValues = explode($this->multivalSeparator, $value);
      foreach($multiValues as $singleValue) {
        $processedVal[] = trim($singleValue);
      }
    } else {
      $processedVal[] = $value;
    }
    return $processedVal;
  }
  
  /**
   * Check if the url is valid one. The method handles single and multi values.
   * 
   * @param $url
   * @return bool
   */
  private function isTypeUrlValid($url):bool
  {
    // @todo: put this method in a more generic class
    if (!empty($url)) {
      // it can happen, that url does have more than one value,
      // so convert all values to an array - this is only to check the values
      // so no changes are necessary
      if (!is_array($url)) {
        $url = [$url];
      }
      foreach($url as $singleUrl) {
        // If only one url is not valid, return false.
        if (!filter_var($singleUrl, FILTER_VALIDATE_URL)) {
          $this->logger->info("url $singleUrl not valid");
          return false;
        }
      }
    }
    return true;
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
        foreach($this->structure as $map=>$colData) {
          // Check if data is set, otherwise ignore.
          if (empty($colData['type'])) {
            throw new \Exception("[$map] No type set in structure.");
          }
          $colType = $colData['type'];
          if (empty($colData['name'])) {
            throw new \Exception("[$map] No name set in structure.");
          }
          $colName = $colData['name'];

          // identifier is a specific type that may not be available in the csv
          // in such cases, set the row line as identifier
          if (($colType == 'identifier') && (!isset($colData['column']))) {
            $importData[$row][$colName] = $row;
          } else {
            // We need the column from where to get the data
            if (!isset($colData['column'])) {
              throw new \Exception("[$map] No column set in structure.");
            }
            $col = $colData['column'];

            if (!empty($colData['necessary'])) {
              // There are some necessary fields, that need to be here
              if (empty($record[$col])) {
                throw new \Exception("Column $colName is necessary but not "
                    . "available in import file $this->csvFile at row $row");
              }
            }

            if (isset($record[$col])) {
              // @todo: don't set empty values?
              $data = $this->getValueFromCsv($record[$col]);

              // If a type cast is set in the config, check if this is valid
              switch($colType) {
                case 'url':
                  if (!$this->isTypeUrlValid($data)) {
                    //throw new \Exception("Url not valid "
                    //    . "in import file $this->csvFile at row $row");
                    $this->logger->critical("Url not valid "
                        . "in import file $this->csvFile at row $row");
                  } else {
                    $importData[$row][$colName] = $data;
                  }
                  break;
                case 'string':
                case 'vocabulary':
                  $importData[$row][$colName] = $data;
                  break;
              }
            } else {
              // If a field is not necessary, it still needs to be set
              // as it will go into the export file.
              // @todo: maybe set it automatically as an empty value?
              throw new \Exception('Import file '. $this->csvFile
                . " does have not set data, row: $row");
            }
          }
        }
      }
    }
    return $importData;
  }

}

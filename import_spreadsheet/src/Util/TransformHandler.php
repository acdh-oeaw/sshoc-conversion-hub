<?php
// src/Util/TransformHandler.php

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

/**
 * Transforming the imported data.
 */
class TransformHandler
{
  private $logger;
  
  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }
  
  /**
   * Processes the value from a csv.
   * Most important: look for multivalues separated (defined in config for wp3)
   * @param string $valueCsv
   * @return array
   */
  private function getValueFromCsv(
      string $valueCsv,
      string $multivalSeparator = ' ; '
  ):array
  {
    $processedVal = [];
    $value = trim($valueCsv);
    // Integrate the column in the json
    // (what we do: switch from left header to upper header)
    // if there is a separator in the value, then it is multivalue
    // - create an array
    if (strpos($value, $multivalSeparator) !== FALSE) {
      $multiValues = explode($multivalSeparator, $value);
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
  public function transformData(
      array $csvRecords,
      int $processFromRow,
      array $structure,
      string $multivalSeparator = ' ; '
  ): array
  {
    $importData = [];

    foreach($csvRecords as $row=>$record) {
      // Ignore rows due to general information not relevant to process.
      // As we set + 1 for offsets, don't use the => here.
      if ($row > $processFromRow) {
        // Go through the structure and gather the data.
        // there are some settings to be aware
        // there can be necessary fields (using "necessary": true), if such a
        // field is empty, raise an exception
        $necessaryNotGiven = false;
        // there can be skipEmpty fields (using "skipEmpty": true), if such a
        // field is empty, don't write the data
        $skipIsSet = false;
        // temporary field that is written if no missing necessary or skipEmpty
        $writeData = [];
        foreach($structure as $colName=>$colData) {
          // Check if data is set, otherwise ignore.
          if (empty($colData['type'])) {
            throw new \Exception("[$colName | $row] No type set in structure.");
          }
          $colType = $colData['type'];

          // Fields may be not available in import, ignore them if the
          // flag ignoreImport is true
          if (!empty($colData['ignoreImport'])) {
            // identifier is a specific type that may not be available in the csv
            // in such cases, set the row line as identifier
            if ($colType == 'identifier') {
              $writeData[$colName] = $row;
            }
          } else {
            // We need the column from where to get the data
            if (!isset($colData['column'])) {
              throw new \Exception("[$colName | $row] No column set in structure.");
            }
            $col = $colData['column'];

            if (!empty($colData['skipEmpty'])) {
              // if there is skipEmpty set, then ignore the dataset
              // @todo: currently the combination of necessary and skipEmpty
              // may lead to unexpected behaviour. if a field is necessary
              // and not set and called before the field with skipEmpty, then
              // there will be an exception. as for the current data no
              // such combination exists, i leave it as it is but it is
              // not a good implementation (fix it later)
              if (empty($record[$col])) {
                // break this for so that the next dataset can be processed.
                // don't give a warning as there could be many rows involved.
                $skipIsSet = true;
                break;
              }
            }
            if (!empty($colData['necessary'])) {
              // There are some necessary fields, that need to be here
              if (empty($record[$col])) {
                $necessaryNotGiven = true;
                // don't break as there could be a skipEmpty setting that
                // would negate the necessary field. but give an error.
                $this->logger->critical("Column $colName is necessary but not "
                    . "available in records at row $row");
              }
            }

            if (isset($record[$col])) {
              // @todo: don't set empty values?
              $data = $this->getValueFromCsv($record[$col], $multivalSeparator);
              $setData = false;
              // If a type cast is set in the config, check if this is valid
              switch($colType) {
                case 'url':
                  if (!$this->isTypeUrlValid($data)) {
                    //throw new \Exception("Url not valid "
                    //    . "in data at row $row");
                    $this->logger->critical("Url not valid "
                        . "in data at row $row");
                  } else {
                    $setData = true;
                  }
                  break;
                case 'string':
                case 'vocabulary':
                  $setData = true;
                  break;
                default:
                  throw new \Exception("Didn't found type $colType "
                    . " in column $colName at row $row");
              }
              if ($setData) {
                $writeData[$colName] = $data;
              }
            } else {
              // If a field is not necessary, it still needs to be set
              // as it will go into the export file.
              // @todo: maybe set it automatically as an empty value?
              throw new \Exception("Data in row $row doesn't have column $col");
            }
          }
        }
        if ($skipIsSet) {
          // don't write the data, go immediately to the next dataset
          continue;
        } elseif ($necessaryNotGiven) {
          // raise an error that a necessary field is not set.
          throw new \Exception("A necessary field is not "
              . "available in records at row $row");
        } else {
          // write the data
          $importData[$row] = $writeData;
        }
      }
    }
    return $importData;
  }
}

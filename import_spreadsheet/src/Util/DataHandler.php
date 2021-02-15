<?php
// src/Util/DataHandler.php

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
 * Handling of the imported data: cleaning, preparing and enrichen it.
 */
class DataHandler
{
  private $logger;
  
  private $importData;
  private $vocabularies;
  
  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }
  
  public function getImportData()
  {
    return $this->importData;
  }
  
  private function isImportDataValid():bool
  {
    if (isset($this->importData)) {
      if (!empty($this->importData)) {
        if (!is_array($this->importData)) {
          throw new \Exception('Import data is not an array.');
        }
      } else {
        throw new \Exception('Import data is empty.');
      }
    } else {
      throw new \Exception('Import data is not set.');
    }
    // As exceptions quit the program, it is safe to return true.
    return true;
  }

  public function setImportData(array $importData)
  {
    $this->importData = $importData;
    $this->isImportDataValid();
  }
  
  /*public function setVocabularyData($colName, $vocabulary)
  {
    // importData needs to be set via the setter
    // check here if the data is valid (there will be a critical exception
    // if something is not valid).
    $this->isImportDataValid();

    foreach($this->importData as $row=>$data) {
      if (!empty($data[$colName])) {
        $boundToVocabulary = [];
        if (!is_array($data[$colName])) {
          $data[$colName] = [$data[$colName]];
        }
        foreach($data[$colName] as $term) {
          $key = array_search($term, $vocabulary);
          if ($key === false) {
            throw new \Exception("Didn't found term $term in row $row"
                . " calling colName $colName");
          } else {
            $boundToVocabulary[] = $key;
          }
        }
        $this->importData[$row][$colName] = $boundToVocabulary;
      }
    }
  }*/
  
  private function setVocabularyTerms()
  {
    foreach($this->importData as $row=>$data) {
      foreach($this->vocabularies as $vocName=>$vocData) {
        $boundToVocabulary = [];
        if (!is_array($data[$vocName])) {
          $data[$vocName] = [$data[$vocName]];
        }
        foreach($data[$vocName] as $term) {
          $key = array_search($term, $vocData);
          if ($key === false) {
            throw new \Exception("Didn't found term $term in row $row"
                . " calling vocName $vocName");
          } else {
            $boundToVocabulary[] = $key;
          }
        }
        $this->importData[$row][$vocName] = $boundToVocabulary;
      }
    }
  }

  private function addTermToVocabulary(string $vocabularyName, string $term)
  {
    if (!isset($this->vocabularies[$vocabularyName])) {
      $this->vocabularies[$vocabularyName] = [];
    }
    if (!in_array($term, $this->vocabularies[$vocabularyName])) {
      $this->vocabularies[$vocabularyName][] = $term;
    }
  }

  public function handleVocabularies($structure)
  {
    $this->isImportDataValid();

    foreach($this->importData as $data) {
      foreach($structure as $map=>$colData) {
        if (!isset($colData['name'])) {
          throw new \Exception("[$map] No name set in structure.");
        }
        $colName = $colData['name'];
        if (($colData['type'] == 'vocabulary') &&
            (!empty($data[$colName]))) {
          $values = $data[$colName];
          if (!is_array($data[$colName])) {
            // if it is not an array, make an array out of it
            $values = [$data[$colName]];
          }
          foreach($values as $value) {
            $this->addTermToVocabulary($colName, $value);
          }
        }
      }
    }
    $this->setVocabularyTerms();
  }
  
  public function showPartsOfData($part)
  {
    print_r($this->vocabularies);
    print_r($this->importData[$part]);
  }

}
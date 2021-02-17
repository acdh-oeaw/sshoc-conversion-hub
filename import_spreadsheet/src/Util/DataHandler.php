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
  private $externalVocabularies;
  
  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }
  
  public function setImportData(array $importData)
  {
    $this->importData = $importData;
    $this->isImportDataValid();
  }
  
  public function getImportData()
  {
    return $this->importData;
  }
  
  public function getVocabulary(string $vocabularyName): array
  {
    if (!isset($this->vocabularies[$vocabularyName])) {
      throw new \Exception("There is no vocabulary called $vocabularyName.");
    }
    // vocabularies created by the values in the data do only
    // have a value field, this value is put into the zero column
    $vocabulary = [];
    foreach($this->vocabularies[$vocabularyName] as $key => $value) {
      $vocabulary[$key] = [
        0 => $value
      ];
    }
    return $vocabulary;
  }
  
  public function setExternalVocabulary(string $vocabularyName,
      array $vocabularyData)
  {
    $this->externalVocabularies[$vocabularyName] = $vocabularyData;
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

  private function setVocabularyTerms()
  {
    foreach($this->importData as $row=>$data) {
      foreach($this->vocabularies as $vocName=>$vocData) {
        $boundToVocabulary = [];
        if (!is_array($data[$vocName])) {
          $data[$vocName] = [$data[$vocName]];
        }
        foreach($data[$vocName] as $term) {
          $term = trim($term);
          if (empty($term)) {
            continue;
          }
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

  private function addTermToVocabulary(string $vocabularyName, string $term,
      $externalId = false)
  {
    if (!isset($this->vocabularies[$vocabularyName])) {
      $this->vocabularies[$vocabularyName] = [];
    }
    if (!in_array($term, $this->vocabularies[$vocabularyName])) {
      if ($externalId !== false) {
        $this->vocabularies[$vocabularyName][$externalId] = $term;
      } else {
        $this->vocabularies[$vocabularyName][] = $term;
      }
    }
  }
  
  private function searchTermInExternalVocabulary(
      string $externalVocabulary,
      string $externalVocabularyTermKey,
      string $searchTerm): int
  {
    if (!isset($this->externalVocabularies[$externalVocabulary])) {
      throw new \Exception("External vocabulary " . $externalVocabulary
          . " not set when searching for a term.");
    }
    foreach($this->externalVocabularies[$externalVocabulary] as $row=>$data) {
      if (empty($data[$externalVocabularyTermKey])) {
        $this->logger->critical("Empty termKey $externalVocabularyTermKey"
            . " in $externalVocabulary at $row");
      } else {
        // make an array if it is a single value so that we can handle
        // multi and single external vocabularies
        if (!is_array($data[$externalVocabularyTermKey])) {
          $data[$externalVocabularyTermKey] = [$data[$externalVocabularyTermKey]];
        }
        foreach($data[$externalVocabularyTermKey] as $compareTerm) {
          if ($compareTerm == $searchTerm) {
            return $row;
          }
        }
      }
    }
    return false;
  }

  public function handleVocabularies(array $structure)
  {
    $this->isImportDataValid();

    foreach($this->importData as $data) {
      foreach($structure as $colName=>$colData) {
        if ((!empty($colData['type']) && $colData['type'] == 'vocabulary') &&
            (!empty($data[$colName]))) {
          $values = $data[$colName];
          if (!is_array($data[$colName])) {
            // if it is not an array, make an array out of it
            $values = [$data[$colName]];
          }
          foreach($values as $value) {
            $value = trim($value);
            if (empty($value)) {
              // ignore empty values
              continue;
            }
            $externalId = false;
            if (!empty($colData['externalVocabulary'])) {
              // respect external vocabulary
              // @todo: put in a dedicated method
              if (!isset(
                $this->externalVocabularies[$colData['externalVocabulary']])) {
                throw new \Exception("External vocabulary "
                    . $colData['externalVocabulary']
                    . " not set from $colName");
              } elseif (empty($colData['externalVocabularyKey'])) {
                throw new \Exception("External vocabulary "
                    . $colData['externalVocabulary']
                    . " does not have an externalVocabularyKey");
              } else {
                // check if the term is in the external vocabulary
                // set it in the internal manifestation
                $externalId = $this->searchTermInExternalVocabulary(
                    $colData['externalVocabulary'],
                    $colData['externalVocabularyKey'],
                    $value);
                if ($externalId === false) {
                  throw new \Exception(
                      "Didn't found $value in external vocabulary "
                      . $colData['externalVocabulary']);
                }
              }
            }
            $this->addTermToVocabulary($colName, $value, $externalId);
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
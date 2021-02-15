<?php
// src/Util/VocabularyHandler.php

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
 * Handling of vocabularies.
 */
class VocabularyHandler
{
  private $logger;
  
  private $vocabularyData = [];

  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }
  
  private function addTerm(string $term)
  {
    if (!in_array($term, $this->vocabularyData)) {
      $this->vocabularyData[] = $term;
    }
  }
  
  public function collectDataFromImportData(array $importData, string $colName)
  {
    foreach($importData as $data) {
      if (!empty($data[$colName])) {
        //print_r($data[$colName]);
        $values = $data[$colName];
        if (!is_array($data[$colName])) {
          // if it is not an array, make an array out of it
          $values = [$data[$colName]];
        }
        foreach($values as $value) {
          $this->addTerm($value);
        }
      }
    }
  }
  
  public function getVocabularyData():array
  {
    return $this->vocabularyData;
  }

}

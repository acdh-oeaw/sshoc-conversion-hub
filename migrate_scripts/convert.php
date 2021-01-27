<?php
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
 * Main controller for conversion of data from spreadsheet into SSHOC WP3
 * conversion hub.
 * 
 * There is currently only one spreadsheet to import.
 * 
 * The behaviour of the script is controlled by a configuration file.
 * It also uses composer to use third-party libraries. You need to do a
 *   composer install
 * when first running the script.
 */

// Integrate and use the composer controlled libraries
$autoload = __DIR__.'/vendor/autoload.php';
if (!file_exists($autoload)) {
  print 'Autoload not found, '
      . "use <i>composer install</i> for initializing<br />\n";
  exit -1;
}
require_once $autoload;

// Integrate the custom libraries
// @todo: should be done by autoload
require_once __DIR__.'/src/Logger.php';
require_once __DIR__.'/src/Configuration.php';
require_once __DIR__.'/src/CsvHandler.php';
require_once __DIR__.'/src/Ingestion.php';

// @todo: also put ingest.php in the namespace SSHOC\MP?
SSHOC\Conversion\Logger::log("Conversion started.");

SSHOC\Conversion\Logger::log("Load configuration.");
$config = new SSHOC\Conversion\Configuration();

SSHOC\Conversion\Logger::log("Convert and ingest spreadsheet.");
$ingest = new SSHOC\Conversion\IngestSpreadsheet($config);
$ingest->runIngest();

SSHOC\Conversion\Logger::log("Conversion ended.");

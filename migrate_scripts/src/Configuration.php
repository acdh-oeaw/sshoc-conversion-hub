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
 * Loading configuration depending on the PROJECT setting.
 */

class Configuration
{
  /**
  * The configuration file. Changes here need to be in accordance with gitignore.
  */
  const CONFIGURATION_FILE = 'conf/configuration.json';

  /**
   * Configuration settings loaded from CONFIGURATION_FILE
   * 
   * @var array
   */
  var $config = null;

  public function __construct()
  {
    // Check if the configuration file is available
    // and load the configuration data.

    if (!file_exists(self::CONFIGURATION_FILE)) {
      Logger::log("ERROR: Configuration file " . self::CONFIGURATION_FILE
          . " does not exist.", __CLASS__.'.'.__FUNCTION__, true);
    }
    $config = json_decode(file_get_contents(self::CONFIGURATION_FILE), true);
    if ($config === FALSE){
      Logger::log("ERROR: Loading data from configuration file "
        . self::CONFIGURATION_FILE . " failed.",
        __CLASS__.'.'.__FUNCTION__, true);
    }
    if (empty($config)) {
      Logger::log("ERROR: No data found in configuration file "
          . self::CONFIGURATION_FILE, __CLASS__.'.'.__FUNCTION__, true);
    }

    $this->config = $config;
  }
  
  public function getConfig($configKey, $necessary = false, $default = false)
  {
    if (isset($this->config[$configKey])) {
      if ($necessary
          && $this->config[$configKey] !== false
          && empty($this->config[$configKey])) {
        Logger::log("ERROR: Empty config key $configKey");
        if ($necessary) {
          exit -1;
        }
      }
      return $this->config[$configKey];
    } else {
      Logger::log(($necessary?'ERROR: ':'') . "Found no config key $configKey");
      if ($necessary) {
        exit -1;
      }
    }
    return $default;
  }

}

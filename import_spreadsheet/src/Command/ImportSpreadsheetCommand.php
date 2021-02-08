<?php
// src/Command/ImportSpreadsheetCommand.php
namespace App\Command;

use App\Service\ImportSpreadsheet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

class ImportSpreadsheetCommand extends Command
{
  protected static $defaultName = 'app:import-spreadsheet';
  
  private $importSpreadsheet;
  
  public function __construct(ImportSpreadsheet $importSpreadsheet)
  {
    $this->importSpreadsheet = $importSpreadsheet;
    
    parent::__construct();
  }
  
  protected function configure()
  {
    $this
        ->setDescription('Import spreadsheet.')
        ->setHelp('Imports from a spreadsheet and creates Drupal migrate data.')
    ;
  }
  
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->writeln([
      'Running import/conversion of spreadsheet',
      '========================================',
      '',
    ]);
    
    $message = $this->importSpreadsheet->runIngest();
    
    $output->writeln($message);
    
    return Command::SUCCESS;
  }
}


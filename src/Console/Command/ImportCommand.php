<?php

namespace IronShark\Typo3DataImporter\Console\Command;

use Doctrine\DBAL\Schema\SchemaException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use IronShark\Typo3DataImporter\Helper;

class ImportCommand extends Command
{
    /** @var \Doctrine\DBAL\Connection */
    protected $db;

    /** @var \Symfony\Component\Console\Input\InputInterface */
    protected $inputInterface;

    /** @var \Symfony\Component\Console\Input\OutputInterface */
    protected $outputInterface;

    /** @var int number of created items */
    protected $createCount = 0;

    /** @var int number of updated items */
    protected $updateCount = 0;

    /**
     * Setup arguments an options
     */
    protected function configure()
    {
        $this
            ->setName('import')
            ->setDescription('Import file to database')
            ->addOption(
                'data-file',
                'd',
                InputOption::VALUE_REQUIRED,
                'Path to data file, or directory with files to be imported.',
                realpath(__DIR__ . "/../../typo3conf/LocalConfiguration.php")
            )
            ->addOption(
                'config-file',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path typo3 configuration file, db configs fill be loaded from this file.',
                realpath(__DIR__ . "/../../typo3conf/LocalConfiguration.php")
            )
            ->addOption(
                'column',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Configuration file path'
            )
            ->addOption(
                'table',
                't',
                InputOption::VALUE_OPTIONAL,
                'Database table name',
                'fe_users'
            )
            ->addOption(
                'map',
                'm',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Data mapping, transform imported values before inserting in database: "column:source_value:target_value" e.g "gender:Herr:1"'
            )
            ->addOption(
                'unique-field',
                'u',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Unique field names, to find entities witch could be updated'
            )
            ->addOption(
                'default',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Default values, to insert some values not listed in the import file.'
            )
            ->addOption(
                'success-directory',
                null,
                InputOption::VALUE_OPTIONAL,
                'Successful imported files will be moved to given directory if option is set.'
            )->addOption(
                'error-directory',
                null,
                InputOption::VALUE_OPTIONAL,
                'Unsuccessful imported files will be moved to given directory if option is set.'
            )->addOption(
                'no-trim',
                null,
                InputOption::VALUE_NONE,
                'Disables trimming of field values.'
            );
    }

    /**
     * Main command entry point
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // init
        $this->inputInterface = $input;
        $this->outputInterface = $output;
        $this->db = Helper::getDbConnection($input->getOption('config-file'));
        $this->db->setAutoCommit(false); // disables auto-commit

        // import file path
        $importFiles = $this->getFilesToImport();

        foreach ($importFiles as $file) {
            $output->writeln(sprintf('<info>Import file: %s</info>', $file));

            try {
                $this->importFile($file);
                $this->db->commit();
                $this->handleSuccess($file);
            } catch (\Exception $e) {
                // rolls back transaction and immediately starts a new one
                $message = sprintf("Unable to import file: %s. rollback transactions", $file);
                $output->writeln(sprintf('<error>%s</error>', $message));
                $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
                $this->db->rollBack();
                $this->handleFailedFile($file);
            }
        }

        // final summary
        $output->writeln(sprintf('<info>Items updated: %s</info>', $this->updateCount));
        $output->writeln(sprintf('<info>Items created: %s</info>', $this->createCount));
    }

    /**
     * Handle successful imported file
     *
     * @param $file
     */
    protected function handleSuccess($file)
    {
        $this->outputInterface->writeln(sprintf('<info>Successfully imported: %s</info>', $file));

        // move file if required
        if ($this->inputInterface->getOption('success-directory')) {
            $path = $this->inputInterface->getOption('success-directory');
            Helper::ensureDirectoryExists($path);
            rename($file, $path . '/' . pathinfo($file, PATHINFO_BASENAME));
        }
    }

    /**
     * Handle file with errors
     *
     * @param $file
     */
    protected function handleFailedFile($file)
    {
        // move file if required
        if ($this->inputInterface->getOption('error-directory')) {
            $path = $this->inputInterface->getOption('error-directory');
            Helper::ensureDirectoryExists($path);
            rename($file, $path . '/' . pathinfo($file, PATHINFO_BASENAME));
        }
    }

    /**
     * Returns table name where data should be imported
     *
     * @return string
     */
    protected function getTableName()
    {
        return $this->inputInterface->getOption('table');
    }

    /**
     * Return all configured column assignments
     * Make possible to assign an import file column to different column in database
     *
     * @return array
     */
    protected function getColumnConfiguration()
    {
        return $this->inputInterface->getOption('column');
    }

    /**
     * Returns value mapping configurations
     *
     * @return array
     */
    protected function getValueMappings()
    {
        return $this->inputInterface->getOption('map');
    }

    /**
     * Returns list of unique fields
     * Used for finding and updating records by list of unique fields
     *
     * @return array
     */
    protected function getUniqueFields()
    {
        return $this->inputInterface->getOption('unique-field');
    }

    /**
     * List of default value assignments in following format:
     * ['field:value', 'language:en']
     *
     * @return array
     */
    protected function getDefaultValues()
    {
        return $this->inputInterface->getOption('default');
    }

    /**
     * Returns true if imported fields should be trimmed
     *
     * @return bool
     */
    protected function trimValues()
    {
        return !$this->inputInterface->getOption('no-trim');
    }

    /**
     * List of files, that needs to bee imported
     *
     * @return array
     */
    protected function getFilesToImport()
    {
        $path = $this->inputInterface->getOption('data-file');
        $path = realpath($path);

        if (is_dir($path)) {
            return glob("$path/*.xlsx");
        }

        return [$path];
    }

    /**
     * Prepare default entity
     *
     * @return array
     */
    protected function getDefaultEntity()
    {
        $entity = [];

        foreach ($this->getDefaultValues() as $defaultValue) {
            list($field, $value) = explode(':', $defaultValue, 2);

            // check if value is dynamic
            if (Helper::isDynamicDefault($value)) {
                $value = Helper::resolveDynamicValue($value);
            }

            $entity[$field] = $value;
        }

        return $entity;
    }

    /**
     * Import file from given path
     *
     * @param $filePath
     */
    protected function importFile($filePath)
    {
        // create file reader
        $reader = Helper::getReaderForFile($filePath);

        // resolve configured column assignments
        $columnAssignments = $this->getColumnAssignments($filePath, $this->getColumnConfiguration());
        $this->checkDatabaseColumns($this->getTableName(), $columnAssignments);

        foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
            // process only the first sheet
            if ($sheetIndex > 1) {
                break;
            }

            $headerRow = [];

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex == 1) {
                    // skip header row
                    $headerRow = $row;
                    continue;
                }

                $this->importRow($rowIndex, array_combine($headerRow, $row), $columnAssignments);
            }
        }
    }

    /**
     * Log row import
     *
     * @param $index
     * @param $row
     */
    private function logRowImport($index, $row)
    {
        if ($this->outputInterface->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->outputInterface->writeln(sprintf('<info>Import row #%s: %s</info>', $index, implode(',', $row)));
        }
    }

    /**
     * Log invalid field format
     *
     * @param $rowIndex
     * @param $entity
     * @param $field
     */
    private function logInvalidRequiredFiled($rowIndex, $entity, $field)
    {
        if ($this->outputInterface->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->outputInterface->writeln(sprintf(
                '<comment>Error in row #%s, required field "%s" is not set: %s</comment>',
                $rowIndex,
                $field,
                implode(',', $entity)
            ));
        }
    }

    /**
     * Update entry found by unique fields or create new
     *
     * @param $row
     * @param $columnAssignments
     * @return int
     */
    protected function importRow($rowIndex, $row, $columnAssignments)
    {
        $entry = $this->getDefaultEntity();

        foreach ($columnAssignments as $dbColumn => $importColumn) {
            $entry[$dbColumn] = $row[$importColumn];
        }

        // apply value transformations (mapping)
        $entry = $this->prepareEntry($entry);

        // try to find to find entity
        $uniqueFields = $this->getUniqueFields();
        $uniqueData = array_intersect_key($entry, array_flip($uniqueFields));

        // skip if required fields are not set
        if (!$this->checkRequiredFields($rowIndex, $entry)) {
            return;
        }

        $this->logRowImport($rowIndex, $entry);

        // check whether entry already exists
        if ($this->entityExists($uniqueData)) {
            $this->updateCount++;
            return $this->db->update($this->getTableName(), $entry, $uniqueData);
        }

        $this->createCount++;
        return $this->db->insert($this->getTableName(), $entry);
    }

    /**
     * Check if all required fields are set
     *
     * @param $rowIndex
     * @param $entity
     * @return bool
     */
    public function checkRequiredFields($rowIndex, $entity)
    {
        $requiredFields = $this->getUniqueFields();

        foreach ($requiredFields as $field) {
            if (!isset($entity[$field]) || empty($entity[$field])) {
                $this->logInvalidRequiredFiled($rowIndex, $entity, $field);
                return false;
            }
        }

        return true;
    }


    /**
     * Check whether entry already exists
     *
     * @param $data
     * @return bool
     */
    protected function entityExists($data)
    {
        $query = $this->db->createQueryBuilder();
        $query->select('*');
        $query->from($this->getTableName());
        foreach ($data as $field => $value) {
            $query->andWhere("$field = :$field");
            $query->setParameter(":$field", $value);
        }

        return $query->execute()->rowCount() > 0;
    }

    /**
     * Apply value mapping, replace entry values
     *
     * @param $entry
     * @return mixed
     */
    protected function prepareEntry($entry)
    {
        foreach ($this->getValueMappings() as $mapping) {
            list($field, $source, $target) = explode(':', $mapping);

            // override entry values
            if ($entry[$field] == $source) {
                $entry[$field] = $target;
            }
        }

        // trim values if required
        if ($this->trimValues()) {
            $entry = array_map('trim', $entry);
        }

        return $entry;
    }

    /**
     * Create database field => import field map
     * [
     *  'last_name => 'Name',
     *  'first_name => 'First Name'
     * ]
     *
     * @param $filePath
     * @param $fieldNameMap
     * @return array
     */
    protected function getColumnAssignments($filePath, $fieldNameMap)
    {
        $columns = [];

        // file header column names with index
        $fileColumns = $this->getDataFileColumns($filePath);

        // add columns with configured mapping
        foreach ($fieldNameMap as $mapItem) {
            list($dbName, $fileName) = explode(':', $mapItem, 2);
            $columns[$dbName] = $fileName;
        }

        // add columns without mapping
        $unmappedColumns = array_diff($fileColumns, array_values($columns));
        foreach ($unmappedColumns as $unmappedColumn) {
            $columns[$unmappedColumn] = $unmappedColumn;
        }

        return $columns;
    }

    /**
     * Return all column names of given file
     *
     * @param $filePath
     * @return mixed
     */
    protected function getDataFileColumns($filePath)
    {
        $reader = Helper::getReaderForFile($filePath);

        foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
            // process only the first sheet
            if ($sheetIndex > 1) {
                break;
            }

            // return first row (header row)
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex == 1) {
                    return $row;
                }
            }
        }
    }

    /**
     * Check whether all imported fields exists
     *
     * @param $db
     * @param $tableName
     * @param $columnAssignments
     * @throws SchemaException
     */
    protected function checkDatabaseColumns($tableName, $columnAssignments)
    {
        $sm = $this->db->getSchemaManager();
        $databaseColumns = array_keys($sm->listTableColumns($tableName));

        foreach ($columnAssignments as $dbColumn => $importColumn) {
            if (!in_array($dbColumn, $databaseColumns)) {
                throw SchemaException::columnDoesNotExist($dbColumn, $tableName);
            }
        }
    }
}
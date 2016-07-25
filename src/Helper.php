<?php
/**
 * Created by PhpStorm.
 * User: antonpauli
 * Date: 11/02/16
 * Time: 12:48
 */

namespace IronShark\Typo3DataImporter;

use PhpParser\Node\Expr\Variable;
use PhpParser\ParserFactory;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;

class Helper
{
    private static $connectionDefaults = [
        'dbname' => 'db',
        'user' => 'root',
        'password' => '',
        'host' => '',
        'driver' => 'pdo_mysql',
        'charset'  => 'utf8',
        'driverOptions' => [1002 => 'SET NAMES utf8']
    ];

    private static $connectionParameterMap = [
        'dbname' => 'typo_db',
        'user' => 'typo_db_username',
        'password' => 'typo_db_password',
        'host' => 'typo_db_host'
    ];

    public static function loadConnectionParams($configFile){

        // parse typo3 config file
        $code = file_get_contents($configFile);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
        $statements = $parser->parse($code);

        // load default default connection parameters
        $connectionParams = self::$connectionDefaults;

        // search connection data in config file
        foreach ($statements as $statement) {

            // skip non variable statements
            if(!isset($statement->var) || !($statement->var instanceof Variable))
                continue;

            // set config values
            if(in_array($statement->var->name, self::$connectionParameterMap)){
                $key = array_search($statement->var->name, self::$connectionParameterMap);
                $connectionParams[$key] = $statement->expr->value;
            }
        }

        return $connectionParams;
    }

    /**
     * Setup database connection
     *
     * @param $configFile
     * @return \Doctrine\DBAL\Connection
     */
    public static function getDbConnection($configFile) {
        $config = new \Doctrine\DBAL\Configuration();
        $connectionParams = self::loadConnectionParams($configFile);
        return \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
    }

    /**
     * Create reader instance
     *
     * @param $filePath
     * @return \Box\Spout\Reader\ReaderInterface
     */
    public static function getReaderForFile($filePath) {
        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($filePath);

        return $reader;
    }

    /**
     * Ensure directory is writable, create if not exists
     *
     * @param $path
     * @throws \Exception
     */
    public static function ensureDirectoryExists($path) {
        if(!file_exists($path))
            mkdir($path, 0777, true);

        if(!is_dir($path) || !is_writeable($path))
            throw new \Exception(sprintf('Unable to write to: %s', $path));
    }
}
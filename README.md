# typo3-data-importer

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

Import data from XLS files into an Typo3 database.

Features

* Single file / directory import
* Customizeable column name mapping
* Dafault values for specified columns
* Generate dynamic defalut values like dates / timestamps on the fly
* Customizable value transformig
* Updates for existing rows
* Moving successful / failed files

## Install

Via Composer

``` bash
$ composer require ironshark/typo3-data-importer
```

## Usage

```bash
php vendor/bin/typo3-data-importer import /path/to/file
```

#### Possible options
| Option            | Example                                                                                                          | Description                                                                                                                                                                                                         |
|-------------------|------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| config-file       | --config-file="/var/www/typo3conf/localconf.php"                                                                 | Path to typo3 config file, used for loading database connection info                                                                                                                                                |
| column            | **multiple values allowed**<br> --column="last_name:Name"<br> --column="first_name:Vorname"                      | Import file column, to db field mapping.<br> Column header name will be used as default database field name, if no mapping configured.<br> Import values from `Name` column (import file) to `last_name` field (db) |
| table             | --table="fe_users"                                                                                               | Database table name, where data should be imported                                                                                                                                                                  |
| map               | **multiple values allowed**<br> --map="gender:Herr:1"<br> --map="gender:Frau:2"<br> --map="gender:Firma:99"      | Field specific value mapping, makes possible to transform values before inserting into the database.<br> For database field `gender` value `Herr` will be transformed to `1`                                        |
| unique-field      | **multiple values allowed**<br> --unique-field="email"                                                           | Unique field names, to find entities witch could be updated                                                                                                                                                         |
| default           | **multiple values allowed**<br> --default="pid:1709"<br> --default="usergroup:4"<br> --default="tstamp:{date:U}" | Default values, to insert values not listed in the import file.                                                                                                                                                     |
| success-directory | --success-directory="/var/www/import/imported"                                                                   | Successful imported files will be moved to given directory                                                                                                                                                          |
| error-directory   | --error-directory="/var/www/import/error"                                                                        | Unsuccessful imported files will be moved to given directory                                                                                                                                                        |
| --no-trim         |                                                                                                                  | Disables value trimming, all values will be trimmed by default                                                                                                                                                      |

#### Dynamic defaults

|Name|Example|Description|
|----|-------|-----------|
|date|--default="tstamp:{date:U}" // 1447057696<br>--default="tstamp:{date:Y-m-d H:i:s} // 2016-07-26 13:14:51"|Generate dynamic default value during populating the databse.<br>Format: --default="db_field:{date:php date formar}"| 

#### Arguments / Shortcuts / Defaults
```bash
Usage:
  import [options] [--] <data-file>

Arguments:
  data-file                                    Path to data file, or directory with files to be imported.

Options:
  -c, --config-file[=CONFIG-FILE]              Path typo3 configuration file, db configs fill be loaded from this file. [default: false]
      --column[=COLUMN]                        Import file column, to db field mapping in following format db_field:ImportFileColumn (multiple values allowed)
  -t, --table[=TABLE]                          Database table name [default: "fe_users"]
  -m, --map[=MAP]                              Data mapping, transform imported values before inserting in database: "column:source_value:target_value" e.g "gender:Herr:1" (multiple values allowed)
  -u, --unique-field[=UNIQUE-FIELD]            Unique field names, to find entities witch could be updated (multiple values allowed)
  -d, --default[=DEFAULT]                      Default values, to insert some values not listed in the import file. (multiple values allowed)
      --success-directory[=SUCCESS-DIRECTORY]  Successful imported files will be moved to given directory if option is set.
      --error-directory[=ERROR-DIRECTORY]      Unsuccessful imported files will be moved to given directory if option is set.
      --no-trim                                Disables trimming of field values.
  -h, --help                                   Display this help message
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi                                   Force ANSI output
      --no-ansi                                Disable ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

#### Example
```bash
php vendor/bin/typo3-data-importer import
    --config-file="/var/www/src/typo3conf/localconf.php"
    --data-file="import"
    --column="last_name:Name"
    --column="first_name:Vorname"
    --column="gender:Anrede"
    --column="email:E-Mailadresse"
    --colu="language:Sprachcode"
    --column="username:E-Mailadresse"
    --map="gender:Herr:1"
    --unique-field="email"
    --default="pid:1709"
    --default="usergroup:4"
    --error-directory="error"
    --default="tstamp:{date:U}"
    --default="password:{date:U}"
```



## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email pauli@ironshark.de instead of using the issue tracker.

## Credits

- [Anton Pauli][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/ironshark/typo3-data-importer.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/ironshark/typo3-data-importer/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/ironshark/typo3-data-importer.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/ironshark/typo3-data-importer.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/ironshark/typo3-data-importer.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/ironshark/typo3-data-importer
[link-travis]: https://travis-ci.org/ironshark/typo3-data-importer
[link-scrutinizer]: https://scrutinizer-ci.com/g/ironshark/typo3-data-importer/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/ironshark/typo3-data-importer
[link-downloads]: https://packagist.org/packages/ironshark/typo3-data-importer
[link-author]: https://github.com/TUNER88
[link-contributors]: ../../contributors

<?php

/**
 * Contact
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/FacebookGallery
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Contact\Data;

use Silex\Application;

class CommunicationType
{

    protected $app = null;
    protected static $table_name = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$table_name = FRAMEWORK_TABLE_PREFIX.'contact_communication_type';
    }

    /**
     * Create the base list
     *
     * @throws \Exception
     */
    public function createTable()
    {
        $table = self::$table_name;
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
        `communication_type_id` INT(11) NOT NULL AUTO_INCREMENT,
        `communication_type_name` VARCHAR(32) NOT NULL DEFAULT '',
        `communication_type_description` VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`communication_type_id`),
        UNIQUE (`communication_type_name`)
        )
    COMMENT='The communication type definition table'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addDebug("Created table 'contact_communication_type'", array('method' => __METHOD__, 'line' => __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    public function initCommunicationTypeList()
    {
        try {
            // get the number of titles in the list
            $count = $this->app['db']->fetchColumn("SELECT COUNT(`communication_type_id`) FROM `".self::$table_name."`");
            if ($count < 1) {
                // no entries!
                $json_import = MANUFAKTUR_PATH.'/Contact/Data/Setup/Import/communication.types.json';
                if (!file_exists($json_import)) {
                    throw new \Exception("Can't read the communication type definition list: $json_import");
                }
                $types = $this->app['utils']->readJSON($json_import);
                foreach ($types as $type) {
                    $this->app['db']->insert(self::$table_name, array(
                        'communication_type_name' => $type['type'],
                        'communication_type_description' => $this->app['utils']->sanitizeText($type['description'])
                    ));
                }
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}
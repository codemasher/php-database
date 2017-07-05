<?php
/**
 * Class PDOSQLiteDriverTest
 *
 * @filesource   PDOSQLiteDriverTest.php
 * @created      28.06.2017
 * @package      chillerlan\DatabaseTest\Drivers\PDO
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */

namespace chillerlan\DatabaseTest\Drivers\PDO;

use chillerlan\Database\Drivers\PDO\PDOSQLiteDriver;

class PDOSQLiteDriverTest extends PDOTestAbstract{

	protected $driver = PDOSQLiteDriver::class;
	protected $envVar = 'DB_SQLITE3_';

}

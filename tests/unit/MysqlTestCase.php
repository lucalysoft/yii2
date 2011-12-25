<?php

namespace yiiunit;

class MysqlTestCase extends TestCase
{
	function __construct()
	{
		if(!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
			$this->markTestSkipped('pdo and pdo_mysql extensions are required.');
		}
	}

	/**
	 * @param bool $reset whether to clean up the test database
	 * @return \yii\db\dao\Connection
	 */
	function getConnection($reset = true)
	{
		$params = $this->getParam('mysql');
		$db = new \yii\db\dao\Connection($params['dsn'], $params['username'], $params['password']);
		if ($reset) {
			$db->active = true;
			$lines = explode(';', file_get_contents($params['fixture']));
			foreach ($lines as $line) {
				if (trim($line) !== '') {
					$db->pdo->exec($line);
				}
			}
		}
		return $db;
	}
}
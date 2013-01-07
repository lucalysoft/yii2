<?php
/**
 * Exception class file.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

/**
 * Exception represents an exception that is caused by some DB-related operations.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Exception extends \yii\base\Exception
{
	/**
	 * @var mixed the error info provided by a PDO exception. This is the same as returned
	 * by [PDO::errorInfo](http://www.php.net/manual/en/pdo.errorinfo.php).
	 */
	public $errorInfo;

	/**
	 * Constructor.
	 * @param string $message PDO error message
	 * @param integer $code PDO error code
	 * @param mixed $errorInfo PDO error info
	 */
	public function __construct($message, $code = 0, $errorInfo = null)
	{
		$this->errorInfo = $errorInfo;
		parent::__construct($message, $code);
	}
}
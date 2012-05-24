<?php
/**
 * ActiveFinder class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2012 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db\ar;

use yii\base\VectorIterator;
use yii\db\dao\Expression;
use yii\db\Exception;

/**
 * 1. eager loading, base limited and has has_many relations
 * 2.
 * ActiveFinder.php is ...
 *
 * @property integer $count
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ActiveQuery extends BaseActiveQuery implements \IteratorAggregate, \ArrayAccess, \Countable
{
	/**
	 * @var string the SQL statement to be executed to retrieve primary records.
	 * This is set by [[ActiveRecord::findBySql()]].
	 */
	public $sql;
	/**
	 * @var array list of query results
	 */
	public $records;

	/**
	 * @param string $modelClass the name of the ActiveRecord class.
	 */
	public function __construct($modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function __call($name, $params)
	{
		$class = $this->modelClass;
		$scopes = $class::scopes();
		if (isset($scopes[$name])) {
			array_unshift($params, $this);
			return call_user_func_array($scopes[$name], $params);
		} else {
			return parent::__call($name, $params);
		}
	}

	/**
	 * Executes query and returns all results as an array.
	 * @return array the query results. If the query results in nothing, an empty array will be returned.
	 */
	public function all()
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		return $this->records;
	}

	/**
	 * Executes query and returns a single row of result.
	 * @return null|array|ActiveRecord the single row of query result. Depending on the setting of [[asArray]],
	 * the query result may be either an array or an ActiveRecord object. Null will be returned
	 * if the query results in nothing.
	 */
	public function one()
	{
		if ($this->records === null) {
			$this->limit = 1;
			$this->records = $this->findRecords();
		}
		return isset($this->records[0]) ? $this->records[0] : null;
	}

	/**
	 * Returns a scalar value for this query.
	 * The value returned will be the first column in the first row of the query results.
	 * @return string|boolean the value of the first column in the first row of the query result.
	 * False is returned if there is no value.
	 */
	public function value()
	{
		return $this->createFinder()->find($this, true);
	}

	/**
	 * Executes query and returns if matching row exists in the table.
	 * @return bool if row exists in the table.
	 */
	public function exists()
	{
		return $this->select(array(new Expression('1')))->value() !== false;
	}

	/**
	 * Returns the database connection used by this query.
	 * This method returns the connection used by the [[modelClass]].
	 * @return \yii\db\dao\Connection the database connection used by this query
	 */
	public function getDbConnection()
	{
		$class = $this->modelClass;
		return $class::getDbConnection();
	}

	/**
	 * Returns the number of items in the vector.
	 * @return integer the number of items in the vector
	 */
	public function getCount()
	{
		return $this->count();
	}

	/**
	 * Sets the parameters about query caching.
	 * This is a shortcut method to {@link CDbConnection::cache()}.
	 * It changes the query caching parameter of the {@link dbConnection} instance.
	 * @param integer $duration the number of seconds that query results may remain valid in cache.
	 * If this is 0, the caching will be disabled.
	 * @param CCacheDependency $dependency the dependency that will be used when saving the query results into cache.
	 * @param integer $queryCount number of SQL queries that need to be cached after calling this method. Defaults to 1,
	 * meaning that the next SQL query will be cached.
	 * @return ActiveRecord the active record instance itself.
	 */
	public function cache($duration, $dependency = null, $queryCount = 1)
	{
		$this->getDbConnection()->cache($duration, $dependency, $queryCount);
		return $this;
	}

	/**
	 * Returns an iterator for traversing the items in the vector.
	 * This method is required by the SPL interface `IteratorAggregate`.
	 * It will be implicitly called when you use `foreach` to traverse the vector.
	 * @return VectorIterator an iterator for traversing the items in the vector.
	 */
	public function getIterator()
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		return new VectorIterator($this->records);
	}

	/**
	 * Returns the number of items in the vector.
	 * This method is required by the SPL `Countable` interface.
	 * It will be implicitly called when you use `count($vector)`.
	 * @return integer number of items in the vector.
	 */
	public function count()
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		return count($this->records);
	}

	/**
	 * Returns a value indicating whether there is an item at the specified offset.
	 * This method is required by the SPL interface `ArrayAccess`.
	 * It is implicitly called when you use something like `isset($vector[$offset])`.
	 * @param integer $offset the offset to be checked
	 * @return boolean whether there is an item at the specified offset.
	 */
	public function offsetExists($offset)
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		return isset($this->records[$offset]);
	}

	/**
	 * Returns the item at the specified offset.
	 * This method is required by the SPL interface `ArrayAccess`.
	 * It is implicitly called when you use something like `$value = $vector[$offset];`.
	 * This is equivalent to [[itemAt]].
	 * @param integer $offset the offset to retrieve item.
	 * @return ActiveRecord the item at the offset
	 * @throws Exception if the offset is out of range
	 */
	public function offsetGet($offset)
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		return isset($this->records[$offset]) ? $this->records[$offset] : null;
	}

	/**
	 * Sets the item at the specified offset.
	 * This method is required by the SPL interface `ArrayAccess`.
	 * It is implicitly called when you use something like `$vector[$offset] = $item;`.
	 * If the offset is null or equal to the number of the existing items,
	 * the new item will be appended to the vector.
	 * Otherwise, the existing item at the offset will be replaced with the new item.
	 * @param integer $offset the offset to set item
	 * @param ActiveRecord $item the item value
	 * @throws Exception if the offset is out of range, or the vector is read only.
	 */
	public function offsetSet($offset, $item)
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		$this->records[$offset] = $item;
	}

	/**
	 * Unsets the item at the specified offset.
	 * This method is required by the SPL interface `ArrayAccess`.
	 * It is implicitly called when you use something like `unset($vector[$offset])`.
	 * This is equivalent to [[removeAt]].
	 * @param integer $offset the offset to unset item
	 * @throws Exception if the offset is out of range, or the vector is read only.
	 */
	public function offsetUnset($offset)
	{
		if ($this->records === null) {
			$this->records = $this->findRecords();
		}
		unset($this->records[$offset]);
	}

	protected function findRecords()
	{
		return $this->createFinder()->find($this);
	}

	protected function createFinder()
	{
		return new ActiveFinder($this->getDbConnection());
	}
}

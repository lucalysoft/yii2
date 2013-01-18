<?php
/**
 * Cache class file.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use yii\base\ApplicationComponent;
use yii\base\InvalidCallException;

/**
 * Cache is the base class for cache classes supporting different cache storage implementation.
 *
 * A data item can be stored in cache by calling [[set()]] and be retrieved back
 * later (in the same or different request) by [[get()]]. In both operations,
 * a key identifying the data item is required. An expiration time and/or a [[Dependency|dependency]]
 * can also be specified when calling [[set()]]. If the data item expires or the dependency
 * changes at the time of calling [[get()]], the cache will return no data.
 *
 * Derived classes should implement the following methods:
 *
 * - [[getValue]]: retrieve the value with a key (if any) from cache
 * - [[setValue]]: store the value with a key into cache
 * - [[addValue]]: store the value only if the cache does not have this key before
 * - [[deleteValue]]: delete the value with the specified key from cache
 * - [[flushValues]]: delete all values from cache
 *
 * Because Cache implements the ArrayAccess interface, it can be used like an array. For example,
 *
 * ~~~
 * $cache['foo'] = 'some data';
 * echo $cache['foo'];
 * ~~~
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Cache extends ApplicationComponent implements \ArrayAccess
{
	/**
	 * @var string a string prefixed to every cache key so that it is unique. Defaults to null, meaning using
	 * the value of [[Application::id]] as the key prefix. You may set this property to be an empty string
	 * if you don't want to use key prefix. It is recommended that you explicitly set this property to some
	 * static value if the cached data needs to be shared among multiple applications.
	 */
	public $keyPrefix;
	/**
	 * @var array|boolean the functions used to serialize and unserialize cached data. Defaults to null, meaning
	 * using the default PHP `serialize()` and `unserialize()` functions. If you want to use some more efficient
	 * serializer (e.g. [igbinary](http://pecl.php.net/package/igbinary)), you may configure this property with
	 * a two-element array. The first element specifies the serialization function, and the second the deserialization
	 * function. If this property is set false, data will be directly sent to and retrieved from the underlying
	 * cache component without any serialization or deserialization. You should not turn off serialization if
	 * you are using [[Dependency|cache dependency]], because it relies on data serialization.
	 */
	public $serializer;

	/**
	 * Initializes the application component.
	 * This method overrides the parent implementation by setting default cache key prefix.
	 */
	public function init()
	{
		parent::init();
		if ($this->keyPrefix === null) {
			$this->keyPrefix = \Yii::$application->id;
		}
	}

	/**
	 * Generates a cache key from one or multiple parameters.
	 * The cache key generated is safe to be used to access cache via methods such as [[get()]], [[set()]].
	 * For example:
	 *
	 * ~~~
	 * $key = Cache::buildKey($className, $method, $id);
	 * ~~~
	 *
	 * @return string the cache key
	 */
	public function generateKey($id)
	{
		$n = func_num_args();
		if ($n === 1 && is_string($id) && ctype_alnum($id) && strlen($id) <= 32) {
			return $this->keyPrefix . $id;
		} elseif ($n < 1) {
			throw new InvalidCallException(__METHOD__ . ' requires at least one parameter.');
		} else {
			$params = func_get_args();
			return $this->keyPrefix . md5(serialize($params));
		}
	}

	/**
	 * Generates a normalized key from a given key.
	 * The normalized key is obtained by hashing the given key via MD5 and then prefixing it with [[keyPrefix]].
	 * @param string $key a key identifying a value to be cached
	 * @return string a key generated from the provided key which ensures the uniqueness across applications
	 */
	protected function generateKey($key)
	{
		return $this->keyPrefix . md5($key);
	}

	/**
	 * Retrieves a value from cache with a specified key.
	 * @param string $id a key identifying the cached value
	 * @return mixed the value stored in cache, false if the value is not in the cache, expired,
	 * or the dependency associated with the cached data has changed.
	 */
	public function get($id)
	{
		$value = $this->getValue($this->generateKey($id));
		if ($value === false || $this->serializer === false) {
			return $value;
		} elseif ($this->serializer === null) {
			$value = unserialize($value);
		} else {
			$value = call_user_func($this->serializer[1], $value);
		}
		if (is_array($value) && ($value[1] instanceof Dependency) || !$value[1]->getHasChanged()) {
			return $value[0];
		} else {
			return false;
		}
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * Some caches (such as memcache, apc) allow retrieving multiple cached values at the same time,
	 * which may improve the performance. In case a cache does not support this feature natively,
	 * this method will try to simulate it.
	 * @param array $ids list of keys identifying the cached values
	 * @return array list of cached values corresponding to the specified keys. The array
	 * is returned in terms of (key,value) pairs.
	 * If a value is not cached or expired, the corresponding array value will be false.
	 */
	public function mget($ids)
	{
		$uids = array();
		foreach ($ids as $id) {
			$uids[$id] = $this->generateKey($id);
		}
		$values = $this->getValues($uids);
		$results = array();
		if ($this->serializer === false) {
			foreach ($uids as $id => $uid) {
				$results[$id] = isset($values[$uid]) ? $values[$uid] : false;
			}
		} else {
			foreach ($uids as $id => $uid) {
				$results[$id] = false;
				if (isset($values[$uid])) {
					$value = $this->serializer === null ? unserialize($values[$uid]) : call_user_func($this->serializer[1], $values[$uid]);
					if (is_array($value) && (!($value[1] instanceof Dependency) || !$value[1]->getHasChanged())) {
						$results[$id] = $value[0];
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Stores a value identified by a key into cache.
	 * If the cache already contains such a key, the existing value and
	 * expiration time will be replaced with the new ones, respectively.
	 *
	 * @param string $id the key identifying the value to be cached
	 * @param mixed $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @param Dependency $dependency dependency of the cached item. If the dependency changes,
	 * the corresponding value in the cache will be invalidated when it is fetched via [[get()]].
	 * @return boolean whether the value is successfully stored into cache
	 */
	public function set($id, $value, $expire = 0, $dependency = null)
	{
		if ($dependency !== null && $this->serializer !== false) {
			$dependency->evaluateDependency();
		}
		if ($this->serializer === null) {
			$value = serialize(array($value, $dependency));
		} elseif ($this->serializer !== false) {
			$value = call_user_func($this->serializer[0], array($value, $dependency));
		}
		return $this->setValue($this->generateKey($id), $value, $expire);
	}

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * Nothing will be done if the cache already contains the key.
	 * @param string $id the key identifying the value to be cached
	 * @param mixed $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @param Dependency $dependency dependency of the cached item. If the dependency changes,
	 * the corresponding value in the cache will be invalidated when it is fetched via [[get()]].
	 * @return boolean whether the value is successfully stored into cache
	 */
	public function add($id, $value, $expire = 0, $dependency = null)
	{
		if ($dependency !== null && $this->serializer !== false) {
			$dependency->evaluateDependency();
		}
		if ($this->serializer === null) {
			$value = serialize(array($value, $dependency));
		} elseif ($this->serializer !== false) {
			$value = call_user_func($this->serializer[0], array($value, $dependency));
		}
		return $this->addValue($this->generateKey($id), $value, $expire);
	}

	/**
	 * Deletes a value with the specified key from cache
	 * @param string $id the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	public function delete($id)
	{
		return $this->deleteValue($this->generateKey($id));
	}

	/**
	 * Deletes all values from cache.
	 * Be careful of performing this operation if the cache is shared among multiple applications.
	 * @return boolean whether the flush operation was successful.
	 */
	public function flush()
	{
		return $this->flushValues();
	}

	/**
	 * Retrieves a value from cache with a specified key.
	 * This method should be implemented by child classes to retrieve the data
	 * from specific cache storage.
	 * @param string $key a unique key identifying the cached value
	 * @return string the value stored in cache, false if the value is not in the cache or expired.
	 */
	abstract protected function getValue($key);

	/**
	 * Stores a value identified by a key in cache.
	 * This method should be implemented by child classes to store the data
	 * in specific cache storage.
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	abstract protected function setValue($key, $value, $expire);

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * This method should be implemented by child classes to store the data
	 * in specific cache storage.
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	abstract protected function addValue($key, $value, $expire);

	/**
	 * Deletes a value with the specified key from cache
	 * This method should be implemented by child classes to delete the data from actual cache storage.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	abstract protected function deleteValue($key);

	/**
	 * Deletes all values from cache.
	 * Child classes may implement this method to realize the flush operation.
	 * @return boolean whether the flush operation was successful.
	 */
	abstract protected function flushValues();

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * The default implementation calls [[getValue()]] multiple times to retrieve
	 * the cached values one by one. If the underlying cache storage supports multiget,
	 * this method should be overridden to exploit that feature.
	 * @param array $keys a list of keys identifying the cached values
	 * @return array a list of cached values indexed by the keys
	 */
	protected function getValues($keys)
	{
		$results = array();
		foreach ($keys as $key) {
			$results[$key] = $this->getValue($key);
		}
		return $results;
	}

	/**
	 * Returns whether there is a cache entry with a specified key.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id a key identifying the cached value
	 * @return boolean
	 */
	public function offsetExists($id)
	{
		return $this->get($id) !== false;
	}

	/**
	 * Retrieves the value from cache with a specified key.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id a key identifying the cached value
	 * @return mixed the value stored in cache, false if the value is not in the cache or expired.
	 */
	public function offsetGet($id)
	{
		return $this->get($id);
	}

	/**
	 * Stores the value identified by a key into cache.
	 * If the cache already contains such a key, the existing value will be
	 * replaced with the new ones. To add expiration and dependencies, use the [[set()]] method.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id the key identifying the value to be cached
	 * @param mixed $value the value to be cached
	 */
	public function offsetSet($id, $value)
	{
		$this->set($id, $value);
	}

	/**
	 * Deletes the value with the specified key from cache
	 * This method is required by the interface ArrayAccess.
	 * @param string $id the key of the value to be deleted
	 */
	public function offsetUnset($id)
	{
		$this->delete($id);
	}
}
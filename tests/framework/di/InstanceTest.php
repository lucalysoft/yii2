<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\di;

use Yii;
use yii\base\Component;
use yii\db\Connection;
use yii\di\Container;
use yii\di\Instance;
use yiiunit\TestCase;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @group di
 */
class InstanceTest extends TestCase
{
    public function testOf()
    {
        $container = new Container;
        $className = Component::class;
        $instance = Instance::of($className);

        $this->assertInstanceOf('\\yii\\di\\Instance', $instance);
        $this->assertInstanceOf(Component::className(), $instance->get($container));
        $this->assertInstanceOf(Component::className(), Instance::ensure($instance, $className, $container));
        $this->assertNotSame($instance->get($container), Instance::ensure($instance, $className, $container));
    }

    public function testEnsure()
    {
        $container = new Container;
        $container->set('db', [
            'class' => 'yii\db\Connection',
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::className(), Instance::ensure('db', 'yii\db\Connection', $container));
        $this->assertInstanceOf(Connection::className(), Instance::ensure(new Connection, 'yii\db\Connection', $container));
        $this->assertInstanceOf('\\yii\\db\\Connection', Instance::ensure(['class' => 'yii\db\Connection', 'dsn' => 'test'], 'yii\db\Connection', $container));
    }

    /**
     * ensure an InvalidConfigException is thrown when a component does not exist.
     */
    public function testEnsure_NonExistingComponentException()
    {
        $container = new Container;
        $this->setExpectedExceptionRegExp('yii\base\InvalidConfigException', '/^Failed to instantiate component or class/i');
        Instance::ensure('cache', 'yii\cache\Cache', $container);
    }

    /**
     * ensure an InvalidConfigException is thrown when a class does not exist.
     */
    public function testEnsure_NonExistingClassException()
    {
        $container = new Container;
        $this->setExpectedExceptionRegExp('yii\base\InvalidConfigException', '/^Failed to instantiate component or class/i');
        Instance::ensure('yii\cache\DoesNotExist', 'yii\cache\Cache', $container);
    }

    public function testEnsure_WithoutType()
    {
        $container = new Container;
        $container->set('db', [
            'class' => 'yii\db\Connection',
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::className(), Instance::ensure('db', null, $container));
        $this->assertInstanceOf(Connection::className(), Instance::ensure(new Connection, null, $container));
        $this->assertInstanceOf('\\yii\\db\\Connection', Instance::ensure(['class' => 'yii\db\Connection', 'dsn' => 'test'], null, $container));
    }

    public function testEnsure_MinimalSettings()
    {
        Yii::$container->set('db', [
            'class' => 'yii\db\Connection',
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::className(), Instance::ensure('db'));
        $this->assertInstanceOf(Connection::className(), Instance::ensure(new Connection));
        $this->assertInstanceOf(Connection::className(), Instance::ensure(['class' => 'yii\db\Connection', 'dsn' => 'test']));
        Yii::$container = new Container;
    }

    public function testExceptionRefersTo()
    {
        $container = new Container;
        $container->set('db', [
            'class' => 'yii\db\Connection',
            'dsn' => 'test',
        ]);

        $this->setExpectedException('yii\base\InvalidConfigException', '"db" refers to a yii\db\Connection component. yii\base\Widget is expected.');

        Instance::ensure('db', 'yii\base\Widget', $container);
        Instance::ensure(['class' => 'yii\db\Connection', 'dsn' => 'test'], 'yii\base\Widget', $container);
    }

    public function testExceptionInvalidDataType()
    {
        $this->setExpectedException('yii\base\InvalidConfigException', 'Invalid data type: yii\db\Connection. yii\base\Widget is expected.');
        Instance::ensure(new Connection, 'yii\base\Widget');
    }

    public function testExceptionComponentIsNotSpecified()
    {
        $this->setExpectedException('yii\base\InvalidConfigException', 'The required component is not specified.');
        Instance::ensure('');
    }

    public function testGet()
    {
        $this->mockApplication([
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'test',
                ]
            ]
        ]);

        $container = Instance::of('db');

        $this->assertInstanceOf(Connection::className(), $container->get());

        $this->destroyApplication();
    }

    /**
     * This tests the usage example given in yii\di\Instance class PHPdoc
     */
    public function testLazyInitializationExample()
    {
        Yii::$container = new Container;
        Yii::$container->set('cache', [
            'class' => 'yii\caching\DbCache',
            'db' => Instance::of('db')
        ]);
        Yii::$container->set('db', [
            'class' => 'yii\db\Connection',
            'dsn' => 'sqlite:path/to/file.db',
        ]);

        $this->assertInstanceOf('yii\caching\DbCache', $cache = Yii::$container->get('cache'));
        $this->assertInstanceOf('yii\db\Connection', $db = $cache->db);
        $this->assertEquals('sqlite:path/to/file.db', $db->dsn);
    }

    public function testRestoreAfterVarExport()
    {
        $instance = Instance::of('something');
        $export = var_export($instance, true);

        $this->assertRegExp(<<<'PHP'
@yii\\di\\Instance::__set_state\(array\(
\s+'id' => 'something',
\)\)@
PHP
        , $export);

        $this->assertEquals($instance, Instance::__set_state([
            'id' => 'something',
        ]));
    }

    public function testRestoreAfterVarExportRequiresId()
    {
        $this->setExpectedException(
            'yii\base\InvalidConfigException',
            'Failed to instantiate class "Instance". Required parameter "id" is missing'
        );

        Instance::__set_state([]);
    }
}

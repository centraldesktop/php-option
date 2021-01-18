<?php

namespace PhpOption\Tests;

use ArrayIterator;
use InvalidArgumentException;
use PhpOption\LazyOption;
use PhpOption\None;
use PhpOption\Option;
use PhpOption\Some;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class LazyOptionTest extends TestCase
{
    private $subject;

    public function setUp()
    {
        $this->subject = $this
            ->getMockBuilder('Subject')
            ->setMethods(array('execute'))
            ->getMock();
    }

    public function testGetWithArgumentsAndConstructor()
    {
        $some = LazyOption::create(array($this->subject, 'execute'), array('foo'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->with('foo')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithArgumentsAndCreate()
    {
        $some = new LazyOption(array($this->subject, 'execute'), array('foo'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->with('foo')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithoutArgumentsAndConstructor()
    {
        $some = new LazyOption(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithoutArgumentsAndCreate()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertTrue($option->isDefined());
        $this->assertFalse($option->isEmpty());
        $this->assertEquals('foo', $option->get());
        $this->assertEquals('foo', $option->getOrElse(null));
        $this->assertEquals('foo', $option->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $option->getOrThrow(new RuntimeException('does_not_exist')));
    }

    public function testCallbackReturnsNull()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(None::create()));

        $this->assertFalse($option->isDefined());
        $this->assertTrue($option->isEmpty());
        $this->assertEquals('alt', $option->getOrElse('alt'));
        $this->assertEquals('alt', $option->getOrCall(function(){return 'alt';}));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("None has no value");

        $option->get();
    }

    public function testExceptionIsThrownIfCallbackReturnsNonOption()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(null));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Expected instance of \PhpOption\Option");

        $this->assertFalse($option->isDefined());
    }

    public function testInvalidCallbackAndConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid callback given");
        new LazyOption('invalidCallback');
    }

    public function testInvalidCallbackAndCreate()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid callback given");
        LazyOption::create('invalidCallback');
    }

    public function testifDefined()
    {
        $called = false;
        $self = $this;
        $this->assertNull(LazyOption::fromValue('foo')->ifDefined(function($v) use (&$called, $self) {
            $called = true;
            $self->assertEquals('foo', $v);
        }));
        $this->assertTrue($called);
    }

    public function testForAll()
    {
        $called = false;
        $self = $this;
        $this->assertInstanceOf('PhpOption\Some', LazyOption::fromValue('foo')->forAll(function($v) use (&$called, $self) {
            $called = true;
            $self->assertEquals('foo', $v);
        }));
        $this->assertTrue($called);
    }

    public function testOrElse()
    {
        $some = Some::create('foo');
        $lazy = LazyOption::create(function() use ($some) {return $some;});
        $this->assertSame($some, $lazy->orElse(None::create()));
        $this->assertSame($some, $lazy->orElse(Some::create('bar')));
    }

    public function testFoldLeftRight()
    {
        $option   = Option::fromValue(5);
        $callback = function($i) { return $i + 1; };

        $lazyOption = new LazyOption(function() use ($option) { return $option; });
        $this->assertSame(6, $lazyOption->foldLeft(5, $callback));

        $lazyOption = new LazyOption(function() use ($option) { return $option; });
        $this->assertSame(6, $lazyOption->foldRight(5, $callback));
    }

    public function testFilterIsOneOf()
    {
        $some     = new Some(new stdClass());
        $lazy_opt = LazyOption::create(function() use ($some) { return $some; });

        $this->assertInstanceOf('PhpOption\None', $lazy_opt->filterIsOneOf('unknown', 'unknown2'));
        $this->assertInstanceOf('PhpOption\None', $lazy_opt->filterIsOneOf(['unknown', 'unknown2']));

        $this->assertSame($some, $lazy_opt->filterIsOneOf(stdClass::class, 'unknown'));
        $this->assertSame($some, $lazy_opt->filterIsOneOf([stdClass::class, 'unknown']));
        $this->assertSame($some, $lazy_opt->filterIsOneOf(new ArrayIterator([stdClass::class, 'unknown'])));
    }

    public function testToString()
    {
        $lazy_opt = LazyOption::create(function() { return Option::fromValue(1); });

        $this->assertEquals('LazyOption(...not evaluated...)', $lazy_opt->__toString());
        $lazy_opt->getOrElse(0);
        $this->assertEquals('LazyOption(Some(1))', $lazy_opt->__toString());
    }
}

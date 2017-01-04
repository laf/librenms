<?php

use Mockery as m;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\JsonSerializable;

class SupportCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testFirstReturnsFirstItemInCollection()
    {
        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals('foo', $c->first());
    }

    public function testFirstWithCallback()
    {
        $data = new Collection(array('foo', 'bar', 'baz'));
        $result = $data->first(function ($value) {
            return $value === 'bar';
        });
        $this->assertEquals('bar', $result);
    }

    public function testFirstWithCallbackAndDefault()
    {
        $data = new Collection(array('foo', 'bar'));
        $result = $data->first(function ($value) {
            return $value === 'baz';
        }, 'default');
        $this->assertEquals('default', $result);
    }

    public function testFirstWithDefaultAndWithoutCallback()
    {
        $data = new Collection;
        $result = $data->first(null, 'default');
        $this->assertEquals('default', $result);
    }

    public function testLastReturnsLastItemInCollection()
    {
        $c = new Collection(array('foo', 'bar'));

        $this->assertEquals('bar', $c->last());
    }

    public function testLastWithCallback()
    {
        $data = new Collection(array(100, 200, 300));
        $result = $data->last(function ($value) {
            return $value < 250;
        });
        $this->assertEquals(200, $result);
        $result = $data->last(function ($value, $key) {
            return $key < 2;
        });
        $this->assertEquals(200, $result);
    }

    public function testLastWithCallbackAndDefault()
    {
        $data = new Collection(array('foo', 'bar'));
        $result = $data->last(function ($value) {
            return $value === 'baz';
        }, 'default');
        $this->assertEquals('default', $result);
    }

    public function testLastWithDefaultAndWithoutCallback()
    {
        $data = new Collection;
        $result = $data->last(null, 'default');
        $this->assertEquals('default', $result);
    }

    public function testPopReturnsAndRemovesLastItemInCollection()
    {
        $c = new Collection(array('foo', 'bar'));

        $this->assertEquals('bar', $c->pop());
        $this->assertEquals('foo', $c->first());
    }

    public function testShiftReturnsAndRemovesFirstItemInCollection()
    {
        $c = new Collection(array('foo', 'bar'));

        $this->assertEquals('foo', $c->shift());
        $this->assertEquals('bar', $c->first());
    }

    public function testEmptyCollectionIsEmpty()
    {
        $c = new Collection();

        $this->assertTrue($c->isEmpty());
    }

    public function testEmptyCollectionIsNotEmpty()
    {
        $c = new Collection(array('foo', 'bar'));

        $this->assertFalse($c->isEmpty());
        $this->assertTrue($c->isNotEmpty());
    }

    public function testCollectionIsConstructed()
    {
        $collection = new Collection('foo');
        $this->assertSame(array('foo'), $collection->all());

        $collection = new Collection(2);
        $this->assertSame(array(2), $collection->all());

        $collection = new Collection(false);
        $this->assertSame(array(false), $collection->all());

        $collection = new Collection(null);
        $this->assertSame(array(), $collection->all());

        $collection = new Collection;
        $this->assertSame(array(), $collection->all());
    }

    public function testGetArrayableItems()
    {
        $collection = new Collection;

        $class = new ReflectionClass($collection);
        $method = $class->getMethod('getArrayableItems');
        $method->setAccessible(true);

        $items = new TestArrayableObject;
        $array = $method->invokeArgs($collection, array($items));
        $this->assertSame(array('foo' => 'bar'), $array);

        $items = new TestJsonableObject;
        $array = $method->invokeArgs($collection, array($items));
        $this->assertSame(array('foo' => 'bar'), $array);

        $items = new TestJsonSerializeObject;
        $array = $method->invokeArgs($collection, array($items));
        $this->assertSame(array('foo' => 'bar'), $array);

        $items = new Collection(array('foo' => 'bar'));
        $array = $method->invokeArgs($collection, array($items));
        $this->assertSame(array('foo' => 'bar'), $array);

        $items = array('foo' => 'bar');
        $array = $method->invokeArgs($collection, array($items));
        $this->assertSame(array('foo' => 'bar'), $array);
    }

    public function testToArrayCallsToArrayOnEachItemInCollection()
    {
        $item1 = m::mock('Illuminate\Contracts\Support\Arrayable');
        $item1->shouldReceive('toArray')->once()->andReturn('foo.array');
        $item2 = m::mock('Illuminate\Contracts\Support\Arrayable');
        $item2->shouldReceive('toArray')->once()->andReturn('bar.array');
        $c = new Collection(array($item1, $item2));
        $results = $c->toArray();

        $this->assertEquals(array('foo.array', 'bar.array'), $results);
    }

    public function testJsonSerializeCallsToArrayOrJsonSerializeOnEachItemInCollection()
    {
        $item1 = m::mock('Illuminate\Contracts\Support\JsonSerializable');
        $item1->shouldReceive('jsonSerialize')->once()->andReturn('foo.json');
        $item2 = m::mock('Illuminate\Contracts\Support\Arrayable');
        $item2->shouldReceive('toArray')->once()->andReturn('bar.array');
        $c = new Collection(array($item1, $item2));
        $results = $c->jsonSerialize();

        $this->assertEquals(array('foo.json', 'bar.array'), $results);
    }

    public function testToJsonEncodesTheJsonSerializeResult()
    {
        $c = $this->getMockBuilder('Illuminate\Support\Collection')->setMethods(array('jsonSerialize'))->getMock();
        $c->expects($this->once())->method('jsonSerialize')->will($this->returnValue('foo'));
        $results = $c->toJson();

        $this->assertJsonStringEqualsJsonString(json_encode('foo'), $results);
    }

    public function testCastingToStringJsonEncodesTheToArrayResult()
    {
        $c = $this->getMockBuilder('Illuminate\Support\Collection')->setMethods(array('jsonSerialize'))->getMock();
        $c->expects($this->once())->method('jsonSerialize')->will($this->returnValue('foo'));

        $this->assertJsonStringEqualsJsonString(json_encode('foo'), (string) $c);
    }

    public function testOffsetAccess()
    {
        $c = new Collection(array('name' => 'taylor'));
        $this->assertEquals('taylor', $c['name']);
        $c['name'] = 'dayle';
        $this->assertEquals('dayle', $c['name']);
        $this->assertTrue(isset($c['name']));
        unset($c['name']);
        $this->assertFalse(isset($c['name']));
        $c[] = 'jason';
        $this->assertEquals('jason', $c[0]);
    }

    public function testArrayAccessOffsetExists()
    {
        $c = new Collection(array('foo', 'bar'));
        $this->assertTrue($c->offsetExists(0));
        $this->assertTrue($c->offsetExists(1));
        $this->assertFalse($c->offsetExists(1000));
    }

    public function testArrayAccessOffsetGet()
    {
        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals('foo', $c->offsetGet(0));
        $this->assertEquals('bar', $c->offsetGet(1));
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Notice
     */
    public function testArrayAccessOffsetGetOnNonExist()
    {
        $c = new Collection(array('foo', 'bar'));
        $c->offsetGet(1000);
    }

    public function testArrayAccessOffsetSet()
    {
        $c = new Collection(array('foo', 'foo'));

        $c->offsetSet(1, 'bar');
        $this->assertEquals('bar', $c[1]);

        $c->offsetSet(null, 'qux');
        $this->assertEquals('qux', $c[2]);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Notice
     */
    public function testArrayAccessOffsetUnset()
    {
        $c = new Collection(array('foo', 'bar'));

        $c->offsetUnset(1);
        $c[1];
    }

    public function testForgetSingleKey()
    {
        $c = new Collection(array('foo', 'bar'));
        $c->forget(0);
        $this->assertFalse(isset($c['foo']));

        $c = new Collection(array('foo' => 'bar', 'baz' => 'qux'));
        $c->forget('foo');
        $this->assertFalse(isset($c['foo']));
    }

    public function testForgetArrayOfKeys()
    {
        $c = new Collection(array('foo', 'bar', 'baz'));
        $c->forget(array(0, 2));
        $this->assertFalse(isset($c[0]));
        $this->assertFalse(isset($c[2]));
        $this->assertTrue(isset($c[1]));

        $c = new Collection(array('name' => 'taylor', 'foo' => 'bar', 'baz' => 'qux'));
        $c->forget(array('foo', 'baz'));
        $this->assertFalse(isset($c['foo']));
        $this->assertFalse(isset($c['baz']));
        $this->assertTrue(isset($c['name']));
    }

    public function testCountable()
    {
        $c = new Collection(array('foo', 'bar'));
        $this->assertCount(2, $c);
    }

    public function testIterable()
    {
        $c = new Collection(array('foo'));
        $this->assertInstanceOf('ArrayIterator', $c->getIterator());
        $this->assertEquals(array('foo'), $c->getIterator()->getArrayCopy());
    }

    public function testCachingIterator()
    {
        $c = new Collection(array('foo'));
        $this->assertInstanceOf('CachingIterator', $c->getCachingIterator());
    }

    public function testFilter()
    {
        $c = new Collection(array(array('id' => 1, 'name' => 'Hello'), array('id' => 2, 'name' => 'World')));
        $this->assertEquals(array(1 => array('id' => 2, 'name' => 'World')), $c->filter(function ($item) {
            return $item['id'] == 2;
        })->all());

        $c = new Collection(array('', 'Hello', '', 'World'));
        $this->assertEquals(array('Hello', 'World'), $c->filter()->values()->toArray());

        $c = new Collection(array('id' => 1, 'first' => 'Hello', 'second' => 'World'));
        $this->assertEquals(array('first' => 'Hello', 'second' => 'World'), $c->filter(function ($item, $key) {
            return $key != 'id';
        })->all());
    }

    public function testWhere()
    {
        $c = new Collection(array(array('v' => 1), array('v' => 2), array('v' => 3), array('v' => '3'), array('v' => 4)));

        $this->assertEquals(
            array(array('v' => 3), array('v' => '3')),
            $c->where('v', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 3), array('v' => '3')),
            $c->where('v', '=', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 3), array('v' => '3')),
            $c->where('v', '==', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 3), array('v' => '3')),
            $c->where('v', 'garbage', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 3)),
            $c->where('v', '===', 3)->values()->all()
        );

        $this->assertEquals(
            array(array('v' => 1), array('v' => 2), array('v' => 4)),
            $c->where('v', '<>', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 1), array('v' => 2), array('v' => 4)),
            $c->where('v', '!=', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 1), array('v' => 2), array('v' => '3'), array('v' => 4)),
            $c->where('v', '!==', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 1), array('v' => 2), array('v' => 3), array('v' => '3')),
            $c->where('v', '<=', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 3), array('v' => '3'), array('v' => 4)),
            $c->where('v', '>=', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 1), array('v' => 2)),
            $c->where('v', '<', 3)->values()->all()
        );
        $this->assertEquals(
            array(array('v' => 4)),
            $c->where('v', '>', 3)->values()->all()
        );
    }

    public function testWhereStrict()
    {
        $c = new Collection(array(array('v' => 3), array('v' => '3')));

        $this->assertEquals(
            array(array('v' => 3)),
            $c->whereStrict('v', 3)->values()->all()
        );
    }

    public function testWhereIn()
    {
        $c = new Collection(array(array('v' => 1), array('v' => 2), array('v' => 3), array('v' => '3'), array('v' => 4)));
        $this->assertEquals(array(array('v' => 1), array('v' => 3), array('v' => '3')), $c->whereIn('v', array(1, 3))->values()->all());
    }

    public function testWhereInStrict()
    {
        $c = new Collection(array(array('v' => 1), array('v' => 2), array('v' => 3), array('v' => '3'), array('v' => 4)));
        $this->assertEquals(array(array('v' => 1), array('v' => 3)), $c->whereInStrict('v', array(1, 3))->values()->all());
    }

    public function testValues()
    {
        $c = new Collection(array(array('id' => 1, 'name' => 'Hello'), array('id' => 2, 'name' => 'World')));
        $this->assertEquals(array(array('id' => 2, 'name' => 'World')), $c->filter(function ($item) {
            return $item['id'] == 2;
        })->values()->all());
    }

    public function testFlatten()
    {
        // Flat arrays are unaffected
        $c = new Collection(array('#foo', '#bar', '#baz'));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Nested arrays are flattened with existing flat items
        $c = new Collection(array(array('#foo', '#bar'), '#baz'));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Sets of nested arrays are flattened
        $c = new Collection(array(array('#foo', '#bar'), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Deeply nested arrays are flattened
        $c = new Collection(array(array('#foo', array('#bar')), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Nested collections are flattened alongside arrays
        $c = new Collection(array(new Collection(array('#foo', '#bar')), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Nested collections containing plain arrays are flattened
        $c = new Collection(array(new Collection(array('#foo', array('#bar'))), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Nested arrays containing collections are flattened
        $c = new Collection(array(array('#foo', new Collection(array('#bar'))), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#baz'), $c->flatten()->all());

        // Nested arrays containing collections containing arrays are flattened
        $c = new Collection(array(array('#foo', new Collection(array('#bar', array('#zap')))), array('#baz')));
        $this->assertEquals(array('#foo', '#bar', '#zap', '#baz'), $c->flatten()->all());
    }

    public function testFlattenWithDepth()
    {
        // No depth flattens recursively
        $c = new Collection(array(array('#foo', array('#bar', array('#baz'))), '#zap'));
        $this->assertEquals(array('#foo', '#bar', '#baz', '#zap'), $c->flatten()->all());

        // Specifying a depth only flattens to that depth
        $c = new Collection(array(array('#foo', array('#bar', array('#baz'))), '#zap'));
        $this->assertEquals(array('#foo', array('#bar', array('#baz')), '#zap'), $c->flatten(1)->all());

        $c = new Collection(array(array('#foo', array('#bar', array('#baz'))), '#zap'));
        $this->assertEquals(array('#foo', '#bar', array('#baz'), '#zap'), $c->flatten(2)->all());
    }

    public function testFlattenIgnoresKeys()
    {
        // No depth ignores keys
        $c = new Collection(array('#foo', array('key' => '#bar'), array('key' => '#baz'), 'key' => '#zap'));
        $this->assertEquals(array('#foo', '#bar', '#baz', '#zap'), $c->flatten()->all());

        // Depth of 1 ignores keys
        $c = new Collection(array('#foo', array('key' => '#bar'), array('key' => '#baz'), 'key' => '#zap'));
        $this->assertEquals(array('#foo', '#bar', '#baz', '#zap'), $c->flatten(1)->all());
    }

    public function testMergeNull()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'Hello'), $c->merge(null)->all());
    }

    public function testMergeArray()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'Hello', 'id' => 1), $c->merge(array('id' => 1))->all());
    }

    public function testMergeCollection()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'World', 'id' => 1), $c->merge(new Collection(array('name' => 'World', 'id' => 1)))->all());
    }

    public function testUnionNull()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'Hello'), $c->union(null)->all());
    }

    public function testUnionArray()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'Hello', 'id' => 1), $c->union(array('id' => 1))->all());
    }

    public function testUnionCollection()
    {
        $c = new Collection(array('name' => 'Hello'));
        $this->assertEquals(array('name' => 'Hello', 'id' => 1), $c->union(new Collection(array('name' => 'World', 'id' => 1)))->all());
    }

    public function testDiffCollection()
    {
        $c = new Collection(array('id' => 1, 'first_word' => 'Hello'));
        $this->assertEquals(array('id' => 1), $c->diff(new Collection(array('first_word' => 'Hello', 'last_word' => 'World')))->all());
    }

    public function testDiffNull()
    {
        $c = new Collection(array('id' => 1, 'first_word' => 'Hello'));
        $this->assertEquals(array('id' => 1, 'first_word' => 'Hello'), $c->diff(null)->all());
    }

    public function testDiffKeys()
    {
        $c1 = new Collection(array('id' => 1, 'first_word' => 'Hello'));
        $c2 = new Collection(array('id' => 123, 'foo_bar' => 'Hello'));
        $this->assertEquals(array('first_word' => 'Hello'), $c1->diffKeys($c2)->all());
    }

    public function testEach()
    {
        $c = new Collection($original = array(1, 2, 'foo' => 'bar', 'bam' => 'baz'));

        $result = array();
        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;
        });
        $this->assertEquals($original, $result);

        $result = array();
        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;
            if (is_string($key)) {
                return false;
            }
        });
        $this->assertEquals(array(1, 2, 'foo' => 'bar'), $result);
    }

    public function testIntersectNull()
    {
        $c = new Collection(array('id' => 1, 'first_word' => 'Hello'));
        $this->assertEquals(array(), $c->intersect(null)->all());
    }

    public function testIntersectCollection()
    {
        $c = new Collection(array('id' => 1, 'first_word' => 'Hello'));
        $this->assertEquals(array('first_word' => 'Hello'), $c->intersect(new Collection(array('first_world' => 'Hello', 'last_word' => 'World')))->all());
    }

    public function testUnique()
    {
        $c = new Collection(array('Hello', 'World', 'World'));
        $this->assertEquals(array('Hello', 'World'), $c->unique()->all());

        $c = new Collection(array(array(1, 2), array(1, 2), array(2, 3), array(3, 4), array(2, 3)));
        $this->assertEquals(array(array(1, 2), array(2, 3), array(3, 4)), $c->unique()->values()->all());
    }

    public function testUniqueWithCallback()
    {
        $c = new Collection(array(
            1 => array('id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'), 2 => array('id' => 2, 'first' => 'Taylor', 'last' => 'Otwell'),
            3 => array('id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'), 4 => array('id' => 4, 'first' => 'Abigail', 'last' => 'Otwell'),
            5 => array('id' => 5, 'first' => 'Taylor', 'last' => 'Swift'), 6 => array('id' => 6, 'first' => 'Taylor', 'last' => 'Swift'),
        ));

        $this->assertEquals(array(
            1 => array('id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'),
            3 => array('id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'),
        ), $c->unique('first')->all());

        $this->assertEquals(array(
            1 => array('id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'),
            3 => array('id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'),
            5 => array('id' => 5, 'first' => 'Taylor', 'last' => 'Swift'),
        ), $c->unique(function ($item) {
            return $item['first'].$item['last'];
        })->all());
    }

    public function testUniqueStrict()
    {
        $c = new Collection(array(
            array(
                'id' => '0',
                'name' => 'zero',
            ),
            array(
                'id' => '00',
                'name' => 'double zero',
            ),
            array(
                'id' => '0',
                'name' => 'again zero',
            ),
        ));

        $this->assertEquals(array(
            array(
                'id' => '0',
                'name' => 'zero',
            ),
            array(
                'id' => '00',
                'name' => 'double zero',
            ),
        ), $c->uniqueStrict('id')->all());
    }

    public function testCollapse()
    {
        $data = new Collection(array(array($object1 = new StdClass), array($object2 = new StdClass)));
        $this->assertEquals(array($object1, $object2), $data->collapse()->all());
    }

    public function testCollapseWithNestedCollactions()
    {
        $data = new Collection(array(new Collection(array(1, 2, 3)), new Collection(array(4, 5, 6))));
        $this->assertEquals(array(1, 2, 3, 4, 5, 6), $data->collapse()->all());
    }

    public function testSort()
    {
        $collection = new Collection(array(5, 3, 1, 2, 4));
        $data = $collection->sort();
        $this->assertEquals(array(1, 2, 3, 4, 5), $data->values()->all());

        $collection1 = new Collection(array(-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4));
        $data = $collection1->sort();
        $this->assertEquals(array(-5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5), $data->values()->all());

        $collection2 = new Collection(array('foo', 'bar-10', 'bar-1'));
        $data = $collection2->sort();
        $this->assertEquals(array('bar-1', 'bar-10', 'foo'), $data->values()->all());
    }

    public function testSortWithCallback()
    {
        $collection = new Collection(array(5, 3, 1, 2, 4));
        $data = $collection->sort(function ($a, $b) {
            if ($a === $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });

        $this->assertEquals(range(1, 5), array_values($data->all()));
    }

    public function testSortBy()
    {
        $data = new Collection(array('taylor', 'dayle'));
        $data = $data->sortBy(function ($x) {
            return $x;
        });

        $this->assertEquals(array('dayle', 'taylor'), array_values($data->all()));

        $data = new Collection(array('dayle', 'taylor'));
        $data = $data->sortByDesc(function ($x) {
            return $x;
        });

        $this->assertEquals(array('taylor', 'dayle'), array_values($data->all()));
    }

    public function testSortByString()
    {
        $data = new Collection(array(array('name' => 'taylor'), array('name' => 'dayle')));
        $data = $data->sortBy('name');

        $this->assertEquals(array(array('name' => 'dayle'), array('name' => 'taylor')), array_values($data->all()));
    }

    public function testSortByAlwaysReturnsAssoc()
    {
        $data = new Collection(array('a' => 'taylor', 'b' => 'dayle'));
        $data = $data->sortBy(function ($x) {
            return $x;
        });

        $this->assertEquals(array('b' => 'dayle', 'a' => 'taylor'), $data->all());

        $data = new Collection(array('taylor', 'dayle'));
        $data = $data->sortBy(function ($x) {
            return $x;
        });

        $this->assertEquals(array(1 => 'dayle', 0 => 'taylor'), $data->all());
    }

    public function testReverse()
    {
        $data = new Collection(array('zaeed', 'alan'));
        $reversed = $data->reverse();

        $this->assertSame(array(1 => 'alan', 0 => 'zaeed'), $reversed->all());

        $data = new Collection(array('name' => 'taylor', 'framework' => 'laravel'));
        $reversed = $data->reverse();

        $this->assertSame(array('framework' => 'laravel', 'name' => 'taylor'), $reversed->all());
    }

    public function testFlip()
    {
        $data = new Collection(array('name' => 'taylor', 'framework' => 'laravel'));
        $this->assertEquals(array('taylor' => 'name', 'laravel' => 'framework'), $data->flip()->toArray());
    }

    public function testChunk()
    {
        $data = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));
        $data = $data->chunk(3);

        $this->assertInstanceOf('Illuminate\Support\Collection', $data);
        $this->assertInstanceOf('Illuminate\Support\Collection', $data[0]);
        $this->assertCount(4, $data);
        $this->assertEquals(array(1, 2, 3), $data[0]->toArray());
        $this->assertEquals(array(9 => 10), $data[3]->toArray());
    }

    public function testChunkWhenGivenZeroAsSize()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));

        $this->assertEquals(
            array(),
            $collection->chunk(0)->toArray()
        );
    }

    public function testChunkWhenGivenLessThanZero()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));

        $this->assertEquals(
            array(),
            $collection->chunk(-1)->toArray()
        );
    }

    public function testEvery()
    {
        $data = new Collection(array(
            6 => 'a',
            4 => 'b',
            7 => 'c',
            1 => 'd',
            5 => 'e',
            3 => 'f',
        ));

        $this->assertEquals(array('a', 'e'), $data->every(4)->all());
        $this->assertEquals(array('b', 'f'), $data->every(4, 1)->all());
        $this->assertEquals(array('c'), $data->every(4, 2)->all());
        $this->assertEquals(array('d'), $data->every(4, 3)->all());
    }

    public function testExcept()
    {
        $data = new Collection(array('first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com'));

        $this->assertEquals(array('first' => 'Taylor'), $data->except(array('last', 'email', 'missing'))->all());
        $this->assertEquals(array('first' => 'Taylor'), $data->except('last', 'email', 'missing')->all());

        $this->assertEquals(array('first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'), $data->except(array('last'))->all());
        $this->assertEquals(array('first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'), $data->except('last')->all());
    }

    public function testPluckWithArrayAndObjectValues()
    {
        $data = new Collection(array((object) array('name' => 'taylor', 'email' => 'foo'), array('name' => 'dayle', 'email' => 'bar')));
        $this->assertEquals(array('taylor' => 'foo', 'dayle' => 'bar'), $data->pluck('email', 'name')->all());
        $this->assertEquals(array('foo', 'bar'), $data->pluck('email')->all());
    }

    public function testPluckWithArrayAccessValues()
    {
        $data = new Collection(array(
            new TestArrayAccessImplementation(array('name' => 'taylor', 'email' => 'foo')),
            new TestArrayAccessImplementation(array('name' => 'dayle', 'email' => 'bar')),
        ));

        $this->assertEquals(array('taylor' => 'foo', 'dayle' => 'bar'), $data->pluck('email', 'name')->all());
        $this->assertEquals(array('foo', 'bar'), $data->pluck('email')->all());
    }

    public function testImplode()
    {
        $data = new Collection(array(array('name' => 'taylor', 'email' => 'foo'), array('name' => 'dayle', 'email' => 'bar')));
        $this->assertEquals('foobar', $data->implode('email'));
        $this->assertEquals('foo,bar', $data->implode('email', ','));

        $data = new Collection(array('taylor', 'dayle'));
        $this->assertEquals('taylordayle', $data->implode(''));
        $this->assertEquals('taylor,dayle', $data->implode(','));
    }

    public function testTake()
    {
        $data = new Collection(array('taylor', 'dayle', 'shawn'));
        $data = $data->take(2);
        $this->assertEquals(array('taylor', 'dayle'), $data->all());
    }

    public function testRandom()
    {
        $data = new Collection(array(1, 2, 3, 4, 5, 6));

        $random = $data->random();
        $this->assertInternalType('integer', $random);
        $this->assertContains($random, $data->all());

        $random = $data->random(3);
        $this->assertInstanceOf('Illuminate\Support\Collection', $random);
        $this->assertCount(3, $random);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRandomThrowsAnErrorWhenRequestingMoreItemsThanAreAvailable()
    {
        $collection = new Collection;
        $collection->random();
    }

    public function testTakeLast()
    {
        $data = new Collection(array('taylor', 'dayle', 'shawn'));
        $data = $data->take(-2);
        $this->assertEquals(array(1 => 'dayle', 2 => 'shawn'), $data->all());
    }
/*
    public function testMacroable()
    {
        // Foo() macro : unique values starting with A
        Collection::macro('foo', function () {
            return $this->filter(function ($item) {
                return strpos($item, 'a') === 0;
            })
                ->unique()
                ->values();
        });

        $c = new Collection(array('a', 'a', 'aa', 'aaa', 'bar'));

        $this->assertSame(array('a', 'aa', 'aaa'), $c->foo()->all());
    }
*/
    public function testMakeMethod()
    {
        $collection = Collection::make('foo');
        $this->assertEquals(array('foo'), $collection->all());
    }

    public function testMakeMethodFromNull()
    {
        $collection = Collection::make(null);
        $this->assertEquals(array(), $collection->all());

        $collection = Collection::make();
        $this->assertEquals(array(), $collection->all());
    }

    public function testMakeMethodFromCollection()
    {
        $firstCollection = Collection::make(array('foo' => 'bar'));
        $secondCollection = Collection::make($firstCollection);
        $this->assertEquals(array('foo' => 'bar'), $secondCollection->all());
    }

    public function testMakeMethodFromArray()
    {
        $collection = Collection::make(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $collection->all());
    }

    public function testConstructMakeFromObject()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $collection = Collection::make($object);
        $this->assertEquals(array('foo' => 'bar'), $collection->all());
    }

    public function testConstructMethod()
    {
        $collection = new Collection('foo');
        $this->assertEquals(array('foo'), $collection->all());
    }

    public function testConstructMethodFromNull()
    {
        $collection = new Collection(null);
        $this->assertEquals(array(), $collection->all());

        $collection = new Collection();
        $this->assertEquals(array(), $collection->all());
    }

    public function testConstructMethodFromCollection()
    {
        $firstCollection = new Collection(array('foo' => 'bar'));
        $secondCollection = new Collection($firstCollection);
        $this->assertEquals(array('foo' => 'bar'), $secondCollection->all());
    }

    public function testConstructMethodFromArray()
    {
        $collection = new Collection(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $collection->all());
    }

    public function testConstructMethodFromObject()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $collection = new Collection($object);
        $this->assertEquals(array('foo' => 'bar'), $collection->all());
    }

    public function testSplice()
    {
        $data = new Collection(array('foo', 'baz'));
        $data->splice(1);
        $this->assertEquals(array('foo'), $data->all());

        $data = new Collection(array('foo', 'baz'));
        $data->splice(1, 0, 'bar');
        $this->assertEquals(array('foo', 'bar', 'baz'), $data->all());

        $data = new Collection(array('foo', 'baz'));
        $data->splice(1, 1);
        $this->assertEquals(array('foo'), $data->all());

        $data = new Collection(array('foo', 'baz'));
        $cut = $data->splice(1, 1, 'bar');
        $this->assertEquals(array('foo', 'bar'), $data->all());
        $this->assertEquals(array('baz'), $cut->all());
    }

    public function testGetPluckValueWithAccessors()
    {
        $model = new TestAccessorEloquentTestStub(array('some' => 'foo'));
        $modelTwo = new TestAccessorEloquentTestStub(array('some' => 'bar'));
        $data = new Collection(array($model, $modelTwo));

        $this->assertEquals(array('foo', 'bar'), $data->pluck('some')->all());
    }

    public function testMap()
    {
        $data = new Collection(array('first' => 'taylor', 'last' => 'otwell'));
        $data = $data->map(function ($item, $key) {
            return $key.'-'.strrev($item);
        });
        $this->assertEquals(array('first' => 'first-rolyat', 'last' => 'last-llewto'), $data->all());
    }

    public function testFlatMap()
    {
        $data = new Collection(array(
            array('name' => 'taylor', 'hobbies' => array('programming', 'basketball')),
            array('name' => 'adam', 'hobbies' => array('music', 'powerlifting')),
        ));
        $data = $data->flatMap(function ($person) {
            return $person['hobbies'];
        });
        $this->assertEquals(array('programming', 'basketball', 'music', 'powerlifting'), $data->all());
    }

    public function testMapWithKeys()
    {
        $data = new Collection(array(
            array('name' => 'Blastoise', 'type' => 'Water', 'idx' => 9),
            array('name' => 'Charmander', 'type' => 'Fire', 'idx' => 4),
            array('name' => 'Dragonair', 'type' => 'Dragon', 'idx' => 148),
        ));
        $data = $data->mapWithKeys(function ($pokemon) {
            return array($pokemon['name'] => $pokemon['type']);
        });
        $this->assertEquals(
            array('Blastoise' => 'Water', 'Charmander' => 'Fire', 'Dragonair' => 'Dragon'),
            $data->all()
        );
    }

    public function testTransform()
    {
        $data = new Collection(array('first' => 'taylor', 'last' => 'otwell'));
        $data->transform(function ($item, $key) {
            return $key.'-'.strrev($item);
        });
        $this->assertEquals(array('first' => 'first-rolyat', 'last' => 'last-llewto'), $data->all());
    }

    public function testGroupByAttribute()
    {
        $data = new Collection(array(array('rating' => 1, 'url' => '1'), array('rating' => 1, 'url' => '1'), array('rating' => 2, 'url' => '2')));

        $result = $data->groupBy('rating');
        $this->assertEquals(array(1 => array(array('rating' => 1, 'url' => '1'), array('rating' => 1, 'url' => '1')), 2 => array(array('rating' => 2, 'url' => '2'))), $result->toArray());

        $result = $data->groupBy('url');
        $this->assertEquals(array(1 => array(array('rating' => 1, 'url' => '1'), array('rating' => 1, 'url' => '1')), 2 => array(array('rating' => 2, 'url' => '2'))), $result->toArray());
    }

    public function testGroupByAttributePreservingKeys()
    {
        $data = new Collection(array(10 => array('rating' => 1, 'url' => '1'),  20 => array('rating' => 1, 'url' => '1'),  30 => array('rating' => 2, 'url' => '2')));

        $result = $data->groupBy('rating', true);

        $expected_result = array(
            1 => array(10 => array('rating' => 1, 'url' => '1'), 20 => array('rating' => 1, 'url' => '1')),
            2 => array(30 => array('rating' => 2, 'url' => '2')),
        );

        $this->assertEquals($expected_result, $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveSingleGroup()
    {
        $data = new Collection(array(array('rating' => 1, 'url' => '1'), array('rating' => 1, 'url' => '1'), array('rating' => 2, 'url' => '2')));

        $result = $data->groupBy(function ($item) {
            return $item['rating'];
        });

        $this->assertEquals(array(1 => array(array('rating' => 1, 'url' => '1'), array('rating' => 1, 'url' => '1')), 2 => array(array('rating' => 2, 'url' => '2'))), $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveSingleGroupPreservingKeys()
    {
        $data = new Collection(array(10 => array('rating' => 1, 'url' => '1'), 20 => array('rating' => 1, 'url' => '1'), 30 => array('rating' => 2, 'url' => '2')));

        $result = $data->groupBy(function ($item) {
            return $item['rating'];
        }, true);

        $expected_result = array(
            1 => array(10 => array('rating' => 1, 'url' => '1'), 20 => array('rating' => 1, 'url' => '1')),
            2 => array(30 => array('rating' => 2, 'url' => '2')),
        );

        $this->assertEquals($expected_result, $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveMultipleGroups()
    {
        $data = new Collection(array(
            array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
            array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
            array('user' => 3, 'roles' => array('Role_1')),
        ));

        $result = $data->groupBy(function ($item) {
            return $item['roles'];
        });

        $expected_result = array(
            'Role_1' => array(
                array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
                array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
                array('user' => 3, 'roles' => array('Role_1')),
            ),
            'Role_2' => array(
                array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
            ),
            'Role_3' => array(
                array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
            ),
        );

        $this->assertEquals($expected_result, $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveMultipleGroupsPreservingKeys()
    {
        $data = new Collection(array(
            10 => array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
            20 => array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
            30 => array('user' => 3, 'roles' => array('Role_1')),
        ));

        $result = $data->groupBy(function ($item) {
            return $item['roles'];
        }, true);

        $expected_result = array(
            'Role_1' => array(
                10 => array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
                20 => array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
                30 => array('user' => 3, 'roles' => array('Role_1')),
            ),
            'Role_2' => array(
                20 => array('user' => 2, 'roles' => array('Role_1', 'Role_2')),
            ),
            'Role_3' => array(
                10 => array('user' => 1, 'roles' => array('Role_1', 'Role_3')),
            ),
        );

        $this->assertEquals($expected_result, $result->toArray());
    }

    public function testKeyByAttribute()
    {
        $data = new Collection(array(array('rating' => 1, 'name' => '1'), array('rating' => 2, 'name' => '2'), array('rating' => 3, 'name' => '3')));

        $result = $data->keyBy('rating');
        $this->assertEquals(array(1 => array('rating' => 1, 'name' => '1'), 2 => array('rating' => 2, 'name' => '2'), 3 => array('rating' => 3, 'name' => '3')), $result->all());

        $result = $data->keyBy(function ($item) {
            return $item['rating'] * 2;
        });
        $this->assertEquals(array(2 => array('rating' => 1, 'name' => '1'), 4 => array('rating' => 2, 'name' => '2'), 6 => array('rating' => 3, 'name' => '3')), $result->all());
    }

    public function testKeyByClosure()
    {
        $data = new Collection(array(
            array('firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'),
            array('firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'),
        ));
        $result = $data->keyBy(function ($item, $key) {
            return strtolower($key.'-'.$item['firstname'].$item['lastname']);
        });
        $this->assertEquals(array(
            '0-taylorotwell' => array('firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'),
            '1-lucasmichot' => array('firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'),
        ), $result->all());
    }

    public function testContains()
    {
        $c = new Collection(array(1, 3, 5));

        $this->assertTrue($c->contains(1));
        $this->assertFalse($c->contains(2));
        $this->assertTrue($c->contains(function ($value) {
            return $value < 5;
        }));
        $this->assertFalse($c->contains(function ($value) {
            return $value > 5;
        }));

        $c = new Collection(array(array('v' => 1), array('v' => 3), array('v' => 5)));

        $this->assertTrue($c->contains('v', 1));
        $this->assertFalse($c->contains('v', 2));

        $c = new Collection(array('date', 'class', (object) array('foo' => 50)));

        $this->assertTrue($c->contains('date'));
        $this->assertTrue($c->contains('class'));
        $this->assertFalse($c->contains('foo'));
    }

    public function testContainsStrict()
    {
        $c = new Collection(array(1, 3, 5, '02'));

        $this->assertTrue($c->containsStrict(1));
        $this->assertFalse($c->containsStrict(2));
        $this->assertTrue($c->containsStrict('02'));
        $this->assertTrue($c->containsStrict(function ($value) {
            return $value < 5;
        }));
        $this->assertFalse($c->containsStrict(function ($value) {
            return $value > 5;
        }));

        $c = new Collection(array(array('v' => 1), array('v' => 3), array('v' => '04'), array('v' => 5)));

        $this->assertTrue($c->containsStrict('v', 1));
        $this->assertFalse($c->containsStrict('v', 2));
        $this->assertFalse($c->containsStrict('v', 4));
        $this->assertTrue($c->containsStrict('v', '04'));

        $c = new Collection(array('date', 'class', (object) array('foo' => 50), ''));

        $this->assertTrue($c->containsStrict('date'));
        $this->assertTrue($c->containsStrict('class'));
        $this->assertFalse($c->containsStrict('foo'));
        $this->assertFalse($c->containsStrict(null));
        $this->assertTrue($c->containsStrict(''));
    }

    public function testGettingSumFromCollection()
    {
        $c = new Collection(array((object) array('foo' => 50), (object) array('foo' => 50)));
        $this->assertEquals(100, $c->sum('foo'));

        $c = new Collection(array((object) array('foo' => 50), (object) array('foo' => 50)));
        $this->assertEquals(100, $c->sum(function ($i) {
            return $i->foo;
        }));
    }

    public function testCanSumValuesWithoutACallback()
    {
        $c = new Collection(array(1, 2, 3, 4, 5));
        $this->assertEquals(15, $c->sum());
    }

    public function testGettingSumFromEmptyCollection()
    {
        $c = new Collection();
        $this->assertEquals(0, $c->sum('foo'));
    }

    public function testValueRetrieverAcceptsDotNotation()
    {
        $c = new Collection(array(
            (object) array('id' => 1, 'foo' => array('bar' => 'B')), (object) array('id' => 2, 'foo' => array('bar' => 'A')),
        ));

        $c = $c->sortBy('foo.bar');
        $this->assertEquals(array(2, 1), $c->pluck('id')->all());
    }

    public function testPullRetrievesItemFromCollection()
    {
        $c = new Collection(array('foo', 'bar'));

        $this->assertEquals('foo', $c->pull(0));
    }

    public function testPullRemovesItemFromCollection()
    {
        $c = new Collection(array('foo', 'bar'));
        $c->pull(0);
        $this->assertEquals(array(1 => 'bar'), $c->all());
    }

    public function testPullReturnsDefault()
    {
        $c = new Collection(array());
        $value = $c->pull(0, 'foo');
        $this->assertEquals('foo', $value);
    }

    public function testRejectRemovesElementsPassingTruthTest()
    {
        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals(array('foo'), $c->reject('bar')->values()->all());

        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals(array('foo'), $c->reject(function ($v) {
            return $v == 'bar';
        })->values()->all());

        $c = new Collection(array('foo', null));
        $this->assertEquals(array('foo'), $c->reject(null)->values()->all());

        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals(array('foo', 'bar'), $c->reject('baz')->values()->all());

        $c = new Collection(array('foo', 'bar'));
        $this->assertEquals(array('foo', 'bar'), $c->reject(function ($v) {
            return $v == 'baz';
        })->values()->all());

        $c = new Collection(array('id' => 1, 'primary' => 'foo', 'secondary' => 'bar'));
        $this->assertEquals(array('primary' => 'foo', 'secondary' => 'bar'), $c->reject(function ($item, $key) {
            return $key == 'id';
        })->all());
    }

    public function testSearchReturnsIndexOfFirstFoundItem()
    {
        $c = new Collection(array(1, 2, 3, 4, 5, 2, 5, 'foo' => 'bar'));

        $this->assertEquals(1, $c->search(2));
        $this->assertEquals('foo', $c->search('bar'));
        $this->assertEquals(4, $c->search(function ($value) {
            return $value > 4;
        }));
        $this->assertEquals('foo', $c->search(function ($value) {
            return ! is_numeric($value);
        }));
    }

    public function testSearchReturnsFalseWhenItemIsNotFound()
    {
        $c = new Collection(array(1, 2, 3, 4, 5, 'foo' => 'bar'));

        $this->assertFalse($c->search(6));
        $this->assertFalse($c->search('foo'));
        $this->assertFalse($c->search(function ($value) {
            return $value < 1 && is_numeric($value);
        }));
        $this->assertFalse($c->search(function ($value) {
            return $value == 'nope';
        }));
    }

    public function testKeys()
    {
        $c = new Collection(array('name' => 'taylor', 'framework' => 'laravel'));
        $this->assertEquals(array('name', 'framework'), $c->keys()->all());
    }

    public function testPaginate()
    {
        $c = new Collection(array('one', 'two', 'three', 'four'));
        $this->assertEquals(array('one', 'two'), $c->forPage(1, 2)->all());
        $this->assertEquals(array(2 => 'three', 3 => 'four'), $c->forPage(2, 2)->all());
        $this->assertEquals(array(), $c->forPage(3, 2)->all());
    }

    public function testPrepend()
    {
        $c = new Collection(array('one', 'two', 'three', 'four'));
        $this->assertEquals(array('zero', 'one', 'two', 'three', 'four'), $c->prepend('zero')->all());

        $c = new Collection(array('one' => 1, 'two' => 2));
        $this->assertEquals(array('zero' => 0, 'one' => 1, 'two' => 2), $c->prepend(0, 'zero')->all());
    }

    public function testZip()
    {
        $c = new Collection(array(1, 2, 3));
        $c = $c->zip(new Collection(array(4, 5, 6)));
        $this->assertInstanceOf('Illuminate\Support\Collection', $c);
        $this->assertInstanceOf('Illuminate\Support\Collection', $c[0]);
        $this->assertInstanceOf('Illuminate\Support\Collection', $c[1]);
        $this->assertInstanceOf('Illuminate\Support\Collection', $c[2]);
        $this->assertCount(3, $c);
        $this->assertEquals(array(1, 4), $c[0]->all());
        $this->assertEquals(array(2, 5), $c[1]->all());
        $this->assertEquals(array(3, 6), $c[2]->all());

        $c = new Collection(array(1, 2, 3));
        $c = $c->zip(array(4, 5, 6), array(7, 8, 9));
        $this->assertCount(3, $c);
        $this->assertEquals(array(1, 4, 7), $c[0]->all());
        $this->assertEquals(array(2, 5, 8), $c[1]->all());
        $this->assertEquals(array(3, 6, 9), $c[2]->all());

        $c = new Collection(array(1, 2, 3));
        $c = $c->zip(array(4, 5, 6), array(7));
        $this->assertCount(3, $c);
        $this->assertEquals(array(1, 4, 7), $c[0]->all());
        $this->assertEquals(array(2, 5, null), $c[1]->all());
        $this->assertEquals(array(3, 6, null), $c[2]->all());
    }

    public function testGettingMaxItemsFromCollection()
    {
        $c = new Collection(array((object) array('foo' => 10), (object) array('foo' => 20)));
        $this->assertEquals(20, $c->max(function ($item) {
            return $item->foo;
        }));
        $this->assertEquals(20, $c->max('foo'));

        $c = new Collection(array(array('foo' => 10), array('foo' => 20)));
        $this->assertEquals(20, $c->max('foo'));

        $c = new Collection(array(1, 2, 3, 4, 5));
        $this->assertEquals(5, $c->max());

        $c = new Collection();
        $this->assertNull($c->max());
    }

    public function testGettingMinItemsFromCollection()
    {
        $c = new Collection(array((object) array('foo' => 10), (object) array('foo' => 20)));
        $this->assertEquals(10, $c->min(function ($item) {
            return $item->foo;
        }));
        $this->assertEquals(10, $c->min('foo'));

        $c = new Collection(array(array('foo' => 10), array('foo' => 20)));
        $this->assertEquals(10, $c->min('foo'));

        $c = new Collection(array(1, 2, 3, 4, 5));
        $this->assertEquals(1, $c->min());

        $c = new Collection(array(1, null, 3, 4, 5));
        $this->assertEquals(1, $c->min());

        $c = new Collection(array(0, 1, 2, 3, 4));
        $this->assertEquals(0, $c->min());

        $c = new Collection();
        $this->assertNull($c->min());
    }

    public function testOnly()
    {
        $data = new Collection(array('first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com'));

        $this->assertEquals($data->all(), $data->only(null)->all());
        $this->assertEquals(array('first' => 'Taylor'), $data->only(array('first', 'missing'))->all());
        $this->assertEquals(array('first' => 'Taylor'), $data->only('first', 'missing')->all());

        $this->assertEquals(array('first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'), $data->only(array('first', 'email'))->all());
        $this->assertEquals(array('first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'), $data->only('first', 'email')->all());
    }

    public function testGettingAvgItemsFromCollection()
    {
        $c = new Collection(array((object) array('foo' => 10), (object) array('foo' => 20)));
        $this->assertEquals(15, $c->avg(function ($item) {
            return $item->foo;
        }));
        $this->assertEquals(15, $c->avg('foo'));

        $c = new Collection(array(array('foo' => 10), array('foo' => 20)));
        $this->assertEquals(15, $c->avg('foo'));

        $c = new Collection(array(1, 2, 3, 4, 5));
        $this->assertEquals(3, $c->avg());

        $c = new Collection();
        $this->assertNull($c->avg());
    }

    public function testJsonSerialize()
    {
        $c = new Collection(array(
            new TestArrayableObject(),
            new TestJsonableObject(),
            new TestJsonSerializeObject(),
            'baz',
        ));

        $this->assertSame(array(
            array('foo' => 'bar'),
            array('foo' => 'bar'),
            array('foo' => 'bar'),
            'baz',
        ), $c->jsonSerialize());
    }

    public function testCombineWithArray()
    {
        $expected = array(
            1 => 4,
            2 => 5,
            3 => 6,
        );

        $c = new Collection(array_keys($expected));
        $actual = $c->combine(array_values($expected))->toArray();

        $this->assertSame($expected, $actual);
    }

    public function testCombineWithCollection()
    {
        $expected = array(
            1 => 4,
            2 => 5,
            3 => 6,
        );

        $keyCollection = new Collection(array_keys($expected));
        $valueCollection = new Collection(array_values($expected));
        $actual = $keyCollection->combine($valueCollection)->toArray();

        $this->assertSame($expected, $actual);
    }

    public function testReduce()
    {
        $data = new Collection(array(1, 2, 3));
        $this->assertEquals(6, $data->reduce(function ($carry, $element) {
            return $carry += $element;
        }));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRandomThrowsAnExceptionUsingAmountBiggerThanCollectionSize()
    {
        $data = new Collection(array(1, 2, 3));
        $data->random(4);
    }

    public function testPipe()
    {
        $collection = new Collection(array(1, 2, 3));

        $this->assertEquals(6, $collection->pipe(function ($collection) {
            return $collection->sum();
        }));
    }

    public function testMedianValueWithArrayCollection()
    {
        $collection = new Collection(array(1, 2, 2, 4));

        $this->assertEquals(2, $collection->median());
    }

    public function testMedianValueByKey()
    {
        $collection = new Collection(array(
            (object) array('foo' => 1),
            (object) array('foo' => 2),
            (object) array('foo' => 2),
            (object) array('foo' => 4),
        ));
        $this->assertEquals(2, $collection->median('foo'));
    }

    public function testEvenMedianCollection()
    {
        $collection = new Collection(array(
            (object) array('foo' => 0),
            (object) array('foo' => 3),
        ));
        $this->assertEquals(1.5, $collection->median('foo'));
    }

    public function testMedianOutOfOrderCollection()
    {
        $collection = new Collection(array(
            (object) array('foo' => 0),
            (object) array('foo' => 5),
            (object) array('foo' => 3),
        ));
        $this->assertEquals(3, $collection->median('foo'));
    }

    public function testMedianOnEmptyCollectionReturnsNull()
    {
        $collection = new Collection();
        $this->assertNull($collection->median());
    }

    public function testModeOnNullCollection()
    {
        $collection = new Collection();
        $this->assertNull($collection->mode());
    }

    public function testMode()
    {
        $collection = new Collection(array(1, 2, 3, 4, 4, 5));
        $this->assertEquals(array(4), $collection->mode());
    }

    public function testModeValueByKey()
    {
        $collection = new Collection(array(
            (object) array('foo' => 1),
            (object) array('foo' => 1),
            (object) array('foo' => 2),
            (object) array('foo' => 4),
        ));
        $this->assertEquals(array(1), $collection->mode('foo'));
    }

    public function testWithMultipleModeValues()
    {
        $collection = new Collection(array(1, 2, 2, 1));
        $this->assertEquals(array(1, 2), $collection->mode());
    }

    public function testSliceOffset()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(4, 5, 6, 7, 8), $collection->slice(3)->values()->toArray());
    }

    public function testSliceNegativeOffset()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(6, 7, 8), $collection->slice(-3)->values()->toArray());
    }

    public function testSliceOffsetAndLength()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(4, 5, 6), $collection->slice(3, 3)->values()->toArray());
    }

    public function testSliceOffsetAndNegativeLength()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(4, 5, 6, 7), $collection->slice(3, -1)->values()->toArray());
    }

    public function testSliceNegativeOffsetAndLength()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(4, 5, 6), $collection->slice(-5, 3)->values()->toArray());
    }

    public function testSliceNegativeOffsetAndNegativeLength()
    {
        $collection = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8));
        $this->assertEquals(array(3, 4, 5, 6), $collection->slice(-6, -2)->values()->toArray());
    }

    public function testCollectionFromTraversable()
    {
        $collection = new Collection(new \ArrayObject(array(1, 2, 3)));
        $this->assertEquals(array(1, 2, 3), $collection->toArray());
    }

    public function testCollectionFromTraversableWithKeys()
    {
        $collection = new Collection(new \ArrayObject(array('foo' => 1, 'bar' => 2, 'baz' => 3)));
        $this->assertEquals(array('foo' => 1, 'bar' => 2, 'baz' => 3), $collection->toArray());
    }

    public function testSplitCollectionWithADivisableCount()
    {
        $collection = new Collection(array('a', 'b', 'c', 'd'));

        $this->assertEquals(
            array(array('a', 'b'), array('c', 'd')),
            $collection->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitCollectionWithAnUndivisableCount()
    {
        $collection = new Collection(array('a', 'b', 'c'));

        $this->assertEquals(
            array(array('a', 'b'), array('c')),
            $collection->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitCollectionWithCountLessThenDivisor()
    {
        $collection = new Collection(array('a'));

        $this->assertEquals(
            array(array('a')),
            $collection->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitEmptyCollection()
    {
        $collection = new Collection();

        $this->assertEquals(
            array(),
            $collection->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testPartitionWithClosure()
    {
        $collection = new Collection(range(1, 10));

        list($firstPartition, $secondPartition) = $collection->partition(function ($i) {
            return $i <= 5;
        });

        $this->assertEquals(array(1, 2, 3, 4, 5), $firstPartition->values()->toArray());
        $this->assertEquals(array(6, 7, 8, 9, 10), $secondPartition->values()->toArray());
    }

    public function testPartitionByKey()
    {
        $courses = new Collection(array(
            array('free' => true, 'title' => 'Basic'), array('free' => false, 'title' => 'Premium'),
        ));

        list($free, $premium) = $courses->partition('free');

        $this->assertSame(array(array('free' => true, 'title' => 'Basic')), $free->values()->toArray());

        $this->assertSame(array(array('free' => false, 'title' => 'Premium')), $premium->values()->toArray());
    }

    public function testPartitionPreservesKeys()
    {
        $courses = new Collection(array(
            'a' => array('free' => true), 'b' => array('free' => false), 'c' => array('free' => true),
        ));

        list($free, $premium) = $courses->partition('free');

        $this->assertSame(array('a' => array('free' => true), 'c' => array('free' => true)), $free->toArray());

        $this->assertSame(array('b' => array('free' => false)), $premium->toArray());
    }

    public function testPartitionEmptyCollection()
    {
        $collection = new Collection();

        $this->assertCount(2, $collection->partition(function () {
            return true;
        }));
    }
}

class TestAccessorEloquentTestStub
{
    protected $attributes = array();

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    public function __get($attribute)
    {
        $accessor = 'get'.lcfirst($attribute).'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        }

        return $this->$attribute;
    }

    public function __isset($attribute)
    {
        $accessor = 'get'.lcfirst($attribute).'Attribute';

        if (method_exists($this, $accessor)) {
            return ! is_null($this->$accessor());
        }

        return isset($this->$attribute);
    }

    public function getSomeAttribute()
    {
        return $this->attributes['some'];
    }
}

class TestArrayAccessImplementation implements ArrayAccess
{
    private $arr;

    public function __construct($arr)
    {
        $this->arr = $arr;
    }

    public function offsetExists($offset)
    {
        return isset($this->arr[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->arr[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->arr[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->arr[$offset]);
    }
}

class TestArrayableObject implements Arrayable
{
    public function toArray()
    {
        return array('foo' => 'bar');
    }
}

class TestJsonableObject implements Jsonable
{
    public function toJson($options = 0)
    {
        return '{"foo":"bar"}';
    }
}

class TestJsonSerializeObject implements JsonSerializable
{
    public function jsonSerialize()
    {
        return array('foo' => 'bar');
    }
}

<?php

/*
 * Copyright 2013-2015 Erudika. http://erudika.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For issues and patches go to: https://github.com/erudika
 */
namespace Para\Tests;

use Para\ParaClient;
use Para\ParaObject;
use Para\Pager;

/**
 * ParaClient Test
 *
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class ParaClientTest extends \PHPUnit_Framework_TestCase {

	private $pc;
	const catsType = "cat";
	const dogsType = "dog";
	const batsType = "bat";

	protected $u;
	protected $u1;
	protected $u2;
	protected $t;
	protected $s1;
	protected $s2;
	protected $a1;
	protected $a2;

	protected function setUp() {
		$this->pc = new ParaClient("app:my-app", "8KXTq2EkZ+6jNyfEHylODMs6jYnEoD+bi/aCzEa+JH+CRvuUI6rWjw==");
		$this->pc->setEndpoint("http://localhost:8080");
		if($this->pc->me() == null) {
			throw new \Exception("Local Para server must be started before testing.");
		}

		$u = new ParaObject("111");
		$u->setName("John Doe");
		$u->setTags(array("one", "two", "three"));

		$u1 = new ParaObject("222");
		$u1->setName("Joe Black");
		$u1->setTags(array("two", "four", "three"));

		$u2 = new ParaObject("333");
		$u2->setName("Ann Smith");
		$u2->setTags(array("four", "five", "three"));

		$t = new ParaObject("test", "tag");
		$t->count = 3;

		$a1 = new ParaObject("adr1", "address");
		$a1->setName("Place 1");
		$a1->setParentid($u->getId());
		$a1->setCreatorid($u->getId());
		$a1->address = "NYC";
		$a1->country = "US";
		$a1->latlng = "40.67,-73.94";

		$a2 = new ParaObject("adr2", "address");
		$a2->setName("Place 2");
		$a2->setParentid($t->getId());
		$a2->setCreatorid($t->getId());
		$a2->address = "NYC";
		$a2->country = "US";
		$a2->latlng = "40.69,-73.95";

		$s1 = new ParaObject("s1");
		$s1->setName("This is a little test sentence. Testing, one, two, three.");

		$s2 = new ParaObject("s2");
		$s2->setName("We are testing this thing. This sentence is a test. One, two.");

		$this->pc->createAll(array($u, $u1, $u2, $t, $s1, $s2, $a1, $a2));
	}

	protected function tearDown() {
	}

	public function testCRUD() {
		$this->assertNull($this->pc->create(null));

		$t1 = $this->pc->create(new ParaObject("test1", "tag"));
		$t1->tag = "test1";
		$this->assertNotNull($t1);

		$this->assertNull($this->pc->read(null, null));
		$this->assertNull($this->pc->read("", ""));

		$trID = $this->pc->read(null, $t1->getId());
		$this->assertNotNull($trID);
		$this->assertNotNull($trID->getTimestamp());
		$this->assertEquals($t1->tag, $trID->tag);

		$tr = $this->pc->read($t1->getType(), $t1->getId());
		$this->assertNotNull($tr);
		$this->assertNotNull($tr->getTimestamp());
		$this->assertEquals($t1->tag, $tr->tag);

		$tr->count = 15;
		$tu = $this->pc->update($tr);
		$this->assertNull($this->pc->update(new ParaObject("null")));
		$this->assertNotNull($tu);
		$this->assertEquals($tu->count, $tr->count);
		$this->assertNotNull($tu->getUpdated());

		$s = new ParaObject();
		$s->setType(self::dogsType);
		$s->foo = "bark!";
		$s = $this->pc->create($s);

		$dog = $this->pc->read(self::dogsType, $s->getId());
		$this->assertTrue(isset($dog->foo));
		$this->assertEquals("bark!", $dog->foo);

		$this->pc->delete($t1);
		$this->pc->delete($dog);
		$this->assertNull($this->pc->read($tr->getType(), $tr->getId()));
	}

	public function testBatchCRUD() {
		$dogs = array();
		for ($i = 0; $i < 3; $i++) {
			$s = new ParaObject();
			$s->setType(self::dogsType);
			$s->foo = "bark!";
			$dogs[$i] = $s;
		}

		$this->assertTrue(empty($this->pc->createAll(null)));
		$l1 = $this->pc->createAll($dogs);
		$this->assertEquals(3, sizeof($l1));
		$this->assertNotNull($l1[0]->getId());

		$this->assertTrue(empty($this->pc->readAll(null)));
		$nl = array();
		$this->assertTrue(empty($this->pc->readAll($nl)));
		$nl[0] = $l1[0]->getId();
		$nl[1] = $l1[1]->getId();
		$nl[2] = $l1[2]->getId();
		$l2 = $this->pc->readAll($nl);
		$this->assertEquals(3, sizeof($l2));
		$this->assertEquals($l1[0]->getId(), $l2[0]->getId());
		$this->assertEquals($l1[1]->getId(), $l2[1]->getId());
		$this->assertTrue(isset($l2[0]->foo));
		$this->assertEquals("bark!", $l2[0]->foo);

		$this->assertTrue(empty($this->pc->updateAll(null)));

		$part1 = new ParaObject($l1[0]->getId());
		$part2 = new ParaObject($l1[1]->getId());
		$part3 = new ParaObject($l1[2]->getId());
		$part1->setType(self::dogsType);
		$part2->setType(self::dogsType);
		$part3->setType(self::dogsType);

		$part1->custom = "prop";
		$part1->setName("NewName1");
		$part2->setName("NewName2");
		$part3->setName("NewName3");

		$l3 = $this->pc->updateAll(array($part1, $part2, $part3));

		$this->assertTrue(isset($l3[0]->custom));
		$this->assertEquals(self::dogsType, $l3[0]->getType());
		$this->assertEquals(self::dogsType, $l3[1]->getType());
		$this->assertEquals(self::dogsType, $l3[2]->getType());

		$this->assertEquals($part1->getName(), $l3[0]->getName());
		$this->assertEquals($part2->getName(), $l3[1]->getName());
		$this->assertEquals($part3->getName(), $l3[2]->getName());

		$this->pc->deleteAll($nl);
		sleep(1);

		$l4 = $this->pc->listObjects(self::dogsType);
		$this->assertTrue(empty($l4));

		$this->assertTrue(in_array(self::dogsType, $this->pc->getApp()->datatypes));
	}

	public function testList() {
		$cats = array();
		for ($i = 0; $i < 3; $i++) {
			$s = new ParaObject(self::catsType.$i);
			$s->setType(self::catsType);
			$cats[$i] = $s;
		}
		$this->pc->createAll($cats);
		sleep(1);

		$this->assertTrue(empty($this->pc->listObjects(null)));
		$this->assertTrue(empty($this->pc->listObjects("")));

		$list1 = $this->pc->listObjects(self::catsType);
		$this->assertFalse(empty($list1));
		$this->assertEquals(3, sizeof($list1));
		$this->assertEquals(self::catsType, $list1[0]->getType());

		$list2 = $this->pc->listObjects(self::catsType, new Pager(1, null, true, 2));
		$this->assertFalse(empty($list2));
		$this->assertEquals(2, sizeof($list2));

		$nl = array();
		$nl[0] = $cats[0]->getId();
		$nl[1] = $cats[1]->getId();
		$nl[2] = $cats[2]->getId();
		$this->pc->deleteAll($nl);

		$this->assertTrue(in_array(self::catsType, $this->pc->getApp()->datatypes));
	}

	public function testTimestamp() {
		$this->assertGreaterThan(0, $this->pc->getTimestamp());
	}

	private function time() {
		return round(microtime(true) * 1000);
	}
}

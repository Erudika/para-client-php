<?php

/*
 * Copyright 2013-2017 Erudika. https://erudika.com
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
use Para\Constraint;

/**
 * ParaClient Test
 *
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class ParaClientTest extends \PHPUnit_Framework_TestCase {

	private $pc;
	private $pc2;
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
		$this->pc = new ParaClient("app:para", "o24K97DuMnRQkI5tGmuQcLiPzWEeoZBQ2FGJdac/ekujQ//lK4LmVw==");
		$this->pc->setEndpoint("http://localhost:8080");
		$this->pc2 = new ParaClient("app:para", null);
		$this->pc2->setEndpoint("http://localhost:8080");
		if($this->pc->me() == null) {
			throw new \Exception("Local Para server must be started before testing.");
		}

		$this->u = new ParaObject("111");
		$this->u->setName("John Doe");

		$this->u->setTags(array("one", "two", "three"));

		$this->u1 = new ParaObject("222");
		$this->u1->setName("Joe Black");
		$this->u1->setTags(array("two", "four", "three"));

		$this->u2 = new ParaObject("333");
		$this->u2->setName("Ann Smith");
		$this->u2->setTags(array("four", "five", "three"));

		$this->t = new ParaObject("tag:test", "tag");
		$this->t->tag = "test";
		$this->t->count = 3;

		$this->a1 = new ParaObject("adr1", "address");
		$this->a1->setName("Place 1");
		$this->a1->setParentid($this->u->getId());
		$this->a1->setCreatorid($this->u->getId());
		$this->a1->address = "NYC";
		$this->a1->country = "US";
		$this->a1->latlng = "40.67,-73.94";

		$this->a2 = new ParaObject("adr2", "address");
		$this->a2->setName("Place 2");
		$this->a2->setParentid($this->t->getId());
		$this->a2->setCreatorid($this->t->getId());
		$this->a2->address = "NYC";
		$this->a2->country = "US";
		$this->a2->latlng = "40.69,-73.95";

		$this->s1 = new ParaObject("s1");
		$this->s1->setName("This is a little test sentence. Testing, one, two, three.");

		$this->s2 = new ParaObject("s2");
		$this->s2->setName("We are testing this thing. This sentence is a test. One, two.");

		$this->pc->createAll(array($this->u, $this->u1, $this->u2, $this->t, $this->s1, $this->s2, $this->a1, $this->a2));
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

	public function testSearch() {
//		ArrayList<Sysprop> bats = new ArrayList<Sysprop>();
//		for (int i = 0; i < 5; i++) {
//			Sysprop s = new Sysprop(batsType + i);
//			s.setType(batsType);
//			s.addProperty("foo", "bat");
//			bats.add(s);
//		}
//		pc.createAll(bats);
		sleep(1);

		$this->assertNull($this->pc->findById(null));
		$this->assertNull($this->pc->findById(""));
		$this->assertNotNull($this->pc->findById($this->u->getId()));
		$this->assertNotNull($this->pc->findById($this->t->getId()));

		$this->assertTrue(empty($this->pc->findByIds(null)));
		$this->assertEquals(3, sizeof($this->pc->findByIds(array($this->u->getId(), $this->u1->getId(), $this->u2->getId()))));

		$this->assertTrue(empty($this->pc->findNearby(null, null, 100, 1, 1)));
		$l1 = $this->pc->findNearby($this->u->getType(), "*", 10, 40.60, -73.90);
		$this->assertFalse(empty($l1));

		$this->assertTrue(empty($this->pc->findNearby(null, null, 100, 1, 1)));
		$l1 = $this->pc->findNearby($this->u->getType(), "*", 10, 40.60, -73.90);
		$this->assertFalse(empty($l1));

		$this->assertTrue(empty($this->pc->findPrefix(null, null, "")));
		$this->assertTrue(empty($this->pc->findPrefix("", "null", "xx")));
		$this->assertFalse(empty($this->pc->findPrefix($this->u->getType(), "name", "ann")));

		//$this->assertFalse(empty($this->pc->findQuery(null, null)));
		$this->assertFalse(empty($this->pc->findQuery("", "*")));
		$this->assertEquals(2, sizeof($this->pc->findQuery($this->a1->getType(), "country:US")));
		$this->assertFalse(empty($this->pc->findQuery($this->u->getType(), "ann")));
		$this->assertFalse(empty($this->pc->findQuery($this->u->getType(), "Ann")));
		$this->assertTrue(sizeof($this->pc->findQuery(null, "*")) > 4);

		$p = new Pager();
		$this->assertEquals(0, $p->count);
		$res = $this->pc->findQuery($this->u->getType(), "*", $p);
		$this->assertEquals(sizeof($res), $p->count);
		$this->assertTrue($p->count > 0);

		$this->assertTrue(empty($this->pc->findSimilar($this->t->getType(), "", null, null)));
		$this->assertTrue(empty($this->pc->findSimilar($this->t->getType(), "", array(), "")));
		$res = $this->pc->findSimilar($this->s1->getType(), $this->s1->getId(), array("name"), $this->s1->getName());
		$this->assertFalse(empty($res));
		$this->assertEquals($this->s2->getId(), $res[0]->getId());

		$i0 = sizeof($this->pc->findTagged($this->u->getType(), null));
		$i1 = sizeof($this->pc->findTagged($this->u->getType(), array("two")));
		$i2 = sizeof($this->pc->findTagged($this->u->getType(), array("one", "two")));
		$i3 = sizeof($this->pc->findTagged($this->u->getType(), array("three")));
		$i4 = sizeof($this->pc->findTagged($this->u->getType(), array("four", "three")));
		$i5 = sizeof($this->pc->findTagged($this->u->getType(), array("five", "three")));
		$i6 = sizeof($this->pc->findTagged($this->t->getType(), array("four", "three")));

		$this->assertEquals(0, $i0);
		$this->assertEquals(2, $i1);
		$this->assertEquals(1, $i2);
		$this->assertEquals(3, $i3);
		$this->assertEquals(2, $i4);
		$this->assertEquals(1, $i5);
		$this->assertEquals(0, $i6);

		$this->assertFalse(empty($this->pc->findTags(null)));
		$this->assertFalse(empty($this->pc->findTags("")));
		$this->assertTrue(empty($this->pc->findTags("unknown")));
		$this->assertTrue(sizeof($this->pc->findTags($this->t->tag)) >= 1);

		$this->assertEquals(3, sizeof($this->pc->findTermInList($this->u->getType(), "id",
				array($this->u->getId(), $this->u1->getId(), $this->u2->getId(), "xxx", "yyy"))));

		// many terms
		$terms = array();
//		$terms["type"] = $this->u->getType();
		$terms["id"] = $this->u->getId();

		$terms1 = array();
		$terms1["type"] = null;
		$terms1["id"] = " ";

		$terms2 = array();
		$terms2[" "] = "bad";
		$terms2[""] = "";

		$this->assertEquals(1, sizeof($this->pc->findTerms($this->u->getType(), $terms, true)));
		$this->assertTrue(empty($this->pc->findTerms($this->u->getType(), $terms1, true)));
		$this->assertTrue(empty($this->pc->findTerms($this->u->getType(), $terms2, true)));

		// single term
		$this->assertTrue(empty($this->pc->findTerms(null, null, true)));
		$this->assertTrue(empty($this->pc->findTerms($this->u->getType(), array("" => null), true)));
		$this->assertTrue(empty($this->pc->findTerms($this->u->getType(), array("" => ""), true)));
		$this->assertTrue(empty($this->pc->findTerms($this->u->getType(), array("term" => null), true)));
		$this->assertTrue(sizeof($this->pc->findTerms($this->u->getType(), array("type" => $this->u->getType()), true)) >= 2);

		$this->assertTrue(empty($this->pc->findWildcard($this->u->getType(), null, null)));
		$this->assertTrue(empty($this->pc->findWildcard($this->u->getType(), "", "")));
		$this->assertFalse(empty($this->pc->findWildcard($this->u->getType(), "name", "an*")));

		$this->assertTrue($this->pc->getCount(null) > 4);
		$this->assertNotEquals(0, $this->pc->getCount(""));
		$this->assertEquals(0, $this->pc->getCount("test"));
		$this->assertTrue($this->pc->getCount($this->u->getType()) >= 3);

		$this->assertEquals(0, $this->pc->getCount(null, null));
		$this->assertEquals(0, $this->pc->getCount($this->u->getType(), array("id" => " ")));
		$this->assertEquals(1, $this->pc->getCount($this->u->getType(), array("id" => $this->u->getId())));
		$this->assertTrue($this->pc->getCount(null, array("type" => $this->u->getType())) > 1);
	}

	public function testLinks() {
		$this->assertNotNull($this->pc->link($this->u, $this->t->getId()));
		$this->assertNotNull($this->pc->link($this->u, $this->u2->getId()));

		$this->assertFalse($this->pc->isLinkedToObject($this->u, null));
		$this->assertTrue($this->pc->isLinkedToObject($this->u, $this->t));
		$this->assertTrue($this->pc->isLinkedToObject($this->u, $this->u2));

		sleep(1);

		$this->assertEquals(1, sizeof($this->pc->getLinkedObjects($this->u, "tag")));
		$this->assertEquals(1, sizeof($this->pc->getLinkedObjects($this->u, "sysprop")));

		$this->assertEquals(0, $this->pc->countLinks($this->u, null));
		$this->assertEquals(1, $this->pc->countLinks($this->u, "tag"));
		$this->assertEquals(1, $this->pc->countLinks($this->u, "sysprop"));

		$this->pc->unlinkAll($this->u);

		$this->assertFalse($this->pc->isLinkedToObject($this->u, $this->t));
		$this->assertFalse($this->pc->isLinkedToObject($this->u, $this->u2));
	}

	public function testUtils() {
		$id1 = $this->pc->newId();
		$id2 = $this->pc->newId();
		$this->assertNotNull($id1);
		$this->assertFalse(empty($id1));
		$this->assertNotEquals($id1, $id2);

		$ts = $this->pc->getTimestamp();
		$this->assertNotNull($ts);
		$this->assertNotEquals(0, $ts);

		$date1 = $this->pc->formatDate("MM dd yyyy", "US");
		$date2 = date("m d Y");
		$this->assertEquals($date2, $date1);

		$ns1 = $this->pc->noSpaces(" test  123		test ", "");
		$this->assertEquals("test123test", $ns1);

		$st1 = $this->pc->stripAndTrim(" %^&*( cool )		@!");
		$this->assertEquals("cool", $st1);

		$md1 = $this->pc->markdownToHtml("#hello **test**");
		$this->assertEquals("<h1>hello <strong>test</strong></h1>\n", $md1);

		$ht1 = $this->pc->approximately(15000);
		$this->assertEquals("15s", $ht1);
	}

	public function testMisc() {
		$types = $this->pc->types();
		$this->assertNotNull($types);
		$this->assertFalse(empty($types));
		$this->assertTrue(array_key_exists("users", $types));

		$this->assertEquals("app:para", $this->pc->me()->getId());
	}

	public function testValidationConstraints() {
		// Validations
		$kittenType = "kitten";
		$constraints = $this->pc->validationConstraints();
		$this->assertFalse(empty($constraints));
		$this->assertTrue(array_key_exists("app", $constraints));
		$this->assertTrue(array_key_exists("user", $constraints));

		$constraint = $this->pc->validationConstraints("app");
		$this->assertFalse(empty($constraint));
		$this->assertTrue(array_key_exists("app", $constraint));
		$this->assertEquals(1, sizeof($constraint));

		$this->pc->addValidationConstraint($kittenType, "paws", Constraint::required());
		$constraint = $this->pc->validationConstraints($kittenType);
		$this->assertTrue(array_key_exists("paws", $constraint[$kittenType]));

		$ct = new ParaObject("felix");
		$ct->setType($kittenType);
		$ct2 = null;
		try {
			// validation fails
			$ct2 = $this->pc->create($ct);
		} catch (\Exception $e) {}

		$this->assertNull($ct2);
		$ct->paws = "4";
		$this->assertNotNull($this->pc->create($ct));

		$this->pc->removeValidationConstraint($kittenType, "paws", "required");
		$constraint = $this->pc->validationConstraints($kittenType);
		$this->assertTrue(empty($constraint));
		$this->assertFalse(array_key_exists($kittenType, $constraint));

		// votes
		$this->assertTrue($this->pc->voteUp($ct, $this->u->getId()));
		$this->assertFalse($this->pc->voteUp($ct, $this->u->getId()));
		$this->assertTrue($this->pc->voteDown($ct, $this->u->getId()));
		$this->assertTrue($this->pc->voteDown($ct, $this->u->getId()));
		$this->assertFalse($this->pc->voteDown($ct, $this->u->getId()));

		$this->pc->delete($ct);
		$this->pc->delete(new ParaObject("vote:".$this->u->getId().":".$ct->getId(), "vote"));

		$this->assertNotNull($this->pc->getServerVersion());
		$this->assertNotEquals("unknown", $this->pc->getServerVersion());
	}

	public function testResourcePermissions() {
		// Permissions
		$permits = $this->pc->resourcePermissions();
		$this->assertNotNull($permits);

		$this->assertTrue(empty($this->pc->grantResourcePermission(null, self::dogsType, array())));
		$this->assertTrue(empty($this->pc->grantResourcePermission(" ", "", array())));

		$this->pc->grantResourcePermission($this->u1->getId(), self::dogsType, array("GET"));
		$permits = $this->pc->resourcePermissions($this->u1->getId());
		$this->assertTrue(array_key_exists($this->u1->getId(), $permits));
		$this->assertTrue(array_key_exists(self::dogsType, $permits[$this->u1->getId()]));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "GET"));
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "POST"));
		// anonymous permissions
		$this->assertFalse($this->pc->isAllowedTo("*", "utils/timestamp", "GET"));
		$this->assertNotNull($this->pc->grantResourcePermission("*", "utils/timestamp", array("GET"), true));
		$this->assertTrue($this->pc2->getTimestamp() > 0);
		$this->assertFalse($this->pc->isAllowedTo("*", "utils/timestamp", "DELETE"));

		$permits = $this->pc->resourcePermissions();
		$this->assertTrue(array_key_exists($this->u1->getId(), $permits));
		$this->assertTrue(array_key_exists(self::dogsType, $permits[$this->u1->getId()]));

		$this->pc->revokeResourcePermission($this->u1->getId(), self::dogsType);
		$permits = $this->pc->resourcePermissions($this->u1->getId());
		$this->assertFalse(array_key_exists(self::dogsType, $permits[$this->u1->getId()]));
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "GET"));
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "POST"));

		$this->pc->grantResourcePermission($this->u2->getId(), "*", array("POST", "PUT", "PATCH", "DELETE"));
		$this->assertTrue($this->pc->isAllowedTo($this->u2->getId(), self::dogsType, "PUT"));
		$this->assertTrue($this->pc->isAllowedTo($this->u2->getId(), self::dogsType, "PATCH"));

		$this->pc->revokeAllResourcePermissions($this->u2->getId());
		$permits = $this->pc->resourcePermissions();
		$this->assertFalse($this->pc->isAllowedTo($this->u2->getId(), self::dogsType, "PUT"));
		$this->assertFalse(empty($permits));
//		$this->assertTrue(empty($permits[$this->u2->getId()]));

		$this->pc->grantResourcePermission($this->u1->getId(), self::dogsType, array("POST", "PUT", "PATCH", "DELETE"));
		$this->pc->grantResourcePermission("*", self::catsType, array("POST", "PUT", "PATCH", "DELETE"));
		$this->pc->grantResourcePermission("*", "*", array("GET"));
		// user-specific permissions are in effect
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "PUT"));
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "GET"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "PUT"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "GET"));

		$this->pc->revokeAllResourcePermissions($this->u1->getId());
		// user-specific permissions not found so check wildcard
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "PUT"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "GET"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "PUT"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "GET"));

		$this->pc->revokeResourcePermission("*", self::catsType);
		// resource-specific permissions not found so check wildcard
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "PUT"));
		$this->assertFalse($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "PUT"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::dogsType, "GET"));
		$this->assertTrue($this->pc->isAllowedTo($this->u1->getId(), self::catsType, "GET"));
		$this->assertTrue($this->pc->isAllowedTo($this->u2->getId(), self::dogsType, "GET"));
		$this->assertTrue($this->pc->isAllowedTo($this->u2->getId(), self::catsType, "GET"));

		$this->pc->revokeAllResourcePermissions("*");
		$this->pc->revokeAllResourcePermissions($this->u1->getId());
	}

	public function testAppSettings() {
		$settings = $this->pc->appSettings();
		$this->assertNotNull($settings);
		$this->assertTrue(empty($settings));

		$this->pc->addAppSetting("", null);
		$this->pc->addAppSetting(" ", " ");
		$this->pc->addAppSetting(null, " ");
		$this->pc->addAppSetting("prop1", 1);
		$this->pc->addAppSetting("prop2", true);
		$this->pc->addAppSetting("prop3", "string");

		$this->assertEquals(3, sizeof($this->pc->appSettings()));
		$this->assertEquals($this->pc->appSettings(), $this->pc->appSettings(null));
		$this->assertEquals(array("value" => 1), $this->pc->appSettings("prop1"));
		$this->assertEquals(array("value" => true), $this->pc->appSettings("prop2"));
		$this->assertEquals(array("value" => "string"), $this->pc->appSettings("prop3"));

		$this->pc->removeAppSetting("prop3");
		$this->pc->removeAppSetting(" ");
		$this->pc->removeAppSetting(null);
		$this->assertTrue(empty($this->pc->appSettings("prop3")));
		$this->assertEquals(2, sizeof($this->pc->appSettings()));
		$this->pc->removeAppSetting("prop2");
		$this->pc->removeAppSetting("prop1");
	}

	public function testAccessTokens() {
		$this->assertNull($this->pc->getAccessToken());
		$this->assertNull($this->pc->signIn("facebook", "test_token"));
		$this->pc->signOut();
		$this->assertFalse($this->pc->revokeAllTokens());
	}

}

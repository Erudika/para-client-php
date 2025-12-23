<?php

/*
 * Copyright 2013-2026 Erudika. https://erudika.com
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
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForLog;
use Throwable;

/**
 * ParaClient Test
 *
 * @author Alex Bogdanovski [alex@erudika.com]
 */
#[AllowDynamicProperties]
class ParaClientTest extends \PHPUnit\Framework\TestCase {

	private const PARA_IMAGE = 'erudikaltd/para:latest_stable';
	private const PARA_PORT = 8080;
	private const ROOT_APP_ID = 'app:para';
	private const ROOT_APP_SECRET = 'xrNQ+OHeZITgWV2w2rFy48LlQROUEijhFMPE99Yfv9EJVXGghfS5SA==';
	private const DEFAULT_ENDPOINT = 'http://localhost:8080';
	private const catsType = "cat";
	private const dogsType = "dog";
	private const batsType = "bat";

	private static ?StartedGenericContainer $paraContainer = null;
	private static string $paraEndpoint = self::DEFAULT_ENDPOINT;

	private static ParaClient $pc;
	private static ParaClient $pc2;
	private static ParaClient $pcChild;

	protected static ParaObject $u;
	protected static ParaObject $u1;
	protected static ParaObject $u2;
	protected static ParaObject $t;
	protected static ParaObject $s1;
	protected static ParaObject $s2;
	protected static ParaObject $a1;
	protected static ParaObject $a2;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		try {
			$container = (new GenericContainer(self::PARA_IMAGE))
				->withExposedPorts(self::PARA_PORT)
				->withEnvironment(['para_root_secret_override' => self::ROOT_APP_SECRET])
				->withWait((new WaitForLog('Started ParaServer'))->withTimeout(60000));

			self::$paraContainer = $container->start();
			$host = self::$paraContainer->getHost();
			$port = self::$paraContainer->getFirstMappedPort();
			self::$paraEndpoint = sprintf('http://%s:%d', $host, $port);

			self::$pc = new ParaClient(self::ROOT_APP_ID, self::ROOT_APP_SECRET);
			self::$pc->setEndpoint(self::$paraEndpoint);
			self::$pc2 = new ParaClient(self::ROOT_APP_ID, null);
			self::$pc2->setEndpoint(self::$paraEndpoint);

			if(self::$pc->me() == null) {
				throw new \Exception("Local Para server must be started before testing.");
			}

			$keys = self::$pc->getEntity(self::$pc->invokeGet("_setup/para-test-child"), true);
			self::$pcChild = new ParaClient("app:para-test-child", $keys["secretKey"]);
			self::$pcChild->setEndpoint(self::$paraEndpoint);

			self::$u = new ParaObject("111");
			self::$u->setName("John Doe");

			self::$u->setTags(array("one", "two", "three"));

			self::$u1 = new ParaObject("222");
			self::$u1->setName("Joe Black");
			self::$u1->setTags(array("two", "four", "three"));

			self::$u2 = new ParaObject("333");
			self::$u2->setName("Ann Smith");
			self::$u2->setTags(array("four", "five", "three"));

			self::$t = new ParaObject("tag:test", "tag");
			self::$t->tag = "test";
			self::$t->count = 3;
			self::$a1 = new ParaObject("adr1", "address");
			self::$a1->setName("Place 1");
			self::$a1->setParentid(self::$u->getId());
			self::$a1->setCreatorid(self::$u->getId());
			self::$a1->address = "NYC";
			self::$a1->country = "US";
			self::$a1->latlng = "40.67,-73.94";

			self::$a2 = new ParaObject("adr2", "address");
			self::$a2->setName("Place 2");
			self::$a2->setParentid(self::$t->getId());
			self::$a2->setCreatorid(self::$t->getId());
			self::$a2->address = "NYC";
			self::$a2->country = "US";
			self::$a2->latlng = "40.69,-73.95";

			self::$s1 = new ParaObject("s1");
			self::$s1->text = "This is a little test sentence. Testing, one, two, three.";

			self::$s2 = new ParaObject("s2");
			self::$s2->text = "We are testing this thing. This sentence is a test. One, two.";

			self::$pc->createAll(array(self::$u, self::$u1, self::$u2, self::$t, self::$s1, self::$s2, self::$a1, self::$a2));

		} catch (Throwable $throwable) {
			self::$paraContainer = null;
			throw new \Exception('Unable to start Para Testcontainer: ' . $throwable->getMessage());
		}
	}

	public static function tearDownAfterClass(): void {
		if (self::$paraContainer !== null) {
			try {
				self::$paraContainer->stop();
			} catch (Throwable $throwable) {
				// ignore shutdown errors, container is going away with the test environment
			} finally {
				self::$paraContainer = null;
			}
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		
	}

	protected function tearDown(): void {
	}

	public function testCRUD() {
		//$this->assertNull(self::$pc->create());

		$t1 = self::$pc->create(new ParaObject("test1", "tag"));
		$t1->tag = "test1";
		$this->assertNotNull($t1);

		$this->assertNull(self::$pc->read(null, null));
		$this->assertNull(self::$pc->read("", ""));

		$trID = self::$pc->read(null, $t1->getId());
		$this->assertNotNull($trID);
		$this->assertNotNull($trID->getTimestamp());
		$this->assertEquals($t1->tag, $trID->tag);

		$tr = self::$pc->read($t1->getType(), $t1->getId());
		$this->assertNotNull($tr);
		$this->assertNotNull($tr->getTimestamp());
		$this->assertEquals($t1->tag, $tr->tag);

		$tr->count = 15;
		$tu = self::$pc->update($tr);
		$this->assertNull(self::$pc->update(new ParaObject("null")));
		$this->assertNotNull($tu);
		$this->assertEquals($tu->count, $tr->count);
		$this->assertNotNull($tu->getUpdated());

		$s = new ParaObject();
		$s->setType(self::dogsType);
		$s->foo = "bark!";
		$s = self::$pc->create($s);

		$dog = self::$pc->read(self::dogsType, $s->getId());
		$this->assertTrue(isset($dog->foo));
		$this->assertEquals("bark!", $dog->foo);

		self::$pc->delete($t1);
		self::$pc->delete($dog);
		$this->assertNull(self::$pc->read($tr->getType(), $tr->getId()));
	}

	public function testBatchCRUD() {
		$dogs = array();
		for ($i = 0; $i < 3; $i++) {
			$s = new ParaObject();
			$s->setType(self::dogsType);
			$s->foo = "bark!";
			$dogs[$i] = $s;
			}

		$this->assertTrue(empty(self::$pc->createAll(null)));
		$l1 = self::$pc->createAll($dogs);
		$this->assertEquals(3, sizeof($l1));
		$this->assertNotNull($l1[0]->getId());

		$this->assertTrue(empty(self::$pc->readAll(null)));
		$nl = array();
		$this->assertTrue(empty(self::$pc->readAll($nl)));
		$nl[0] = $l1[0]->getId();
		$nl[1] = $l1[1]->getId();
		$nl[2] = $l1[2]->getId();
		$l2 = self::$pc->readAll($nl);
		$this->assertEquals(3, sizeof($l2));
		$this->assertEquals($l1[0]->getId(), $l2[0]->getId());
		$this->assertEquals($l1[1]->getId(), $l2[1]->getId());
		$this->assertTrue(isset($l2[0]->foo));
		$this->assertEquals("bark!", $l2[0]->foo);

		$this->assertTrue(empty(self::$pc->updateAll(null)));

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

		$l3 = self::$pc->updateAll(array($part1, $part2, $part3));

		$this->assertTrue(isset($l3[0]->custom));
		$this->assertEquals(self::dogsType, $l3[0]->getType());
		$this->assertEquals(self::dogsType, $l3[1]->getType());
		$this->assertEquals(self::dogsType, $l3[2]->getType());

		$this->assertEquals($part1->getName(), $l3[0]->getName());
		$this->assertEquals($part2->getName(), $l3[1]->getName());
		$this->assertEquals($part3->getName(), $l3[2]->getName());

		self::$pc->deleteAll($nl);
		sleep(1);

		$l4 = self::$pc->listObjects(self::dogsType);
		$this->assertTrue(empty($l4));

		$this->assertTrue(in_array(self::dogsType, self::$pc->getApp()->datatypes));
	}

	public function testList() {
		$cats = array();
		for ($i = 0; $i < 3; $i++) {
			$s = new ParaObject(self::catsType.$i);
			$s->setType(self::catsType);
			$cats[$i] = $s;
		}
		self::$pc->createAll($cats);
		sleep(1);

		$this->assertTrue(empty(self::$pc->listObjects("null")));
		$this->assertTrue(empty(self::$pc->listObjects("")));

		$list1 = self::$pc->listObjects(self::catsType);
		$this->assertFalse(empty($list1));
		$this->assertEquals(3, sizeof($list1));
		$this->assertEquals(self::catsType, $list1[0]->getType());

		$list2 = self::$pc->listObjects(self::catsType, new Pager(1, null, true, 2));
		$this->assertFalse(empty($list2));
		$this->assertEquals(2, sizeof($list2));

		$nl = array();
		$nl[0] = $cats[0]->getId();
		$nl[1] = $cats[1]->getId();
		$nl[2] = $cats[2]->getId();
		self::$pc->deleteAll($nl);

		$this->assertTrue(in_array(self::catsType, self::$pc->getApp()->datatypes));
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

		$this->assertNull(self::$pc->findById("null"));
		$this->assertNull(self::$pc->findById(""));
		$this->assertNotNull(self::$pc->findById(self::$u->getId()));
		$this->assertNotNull(self::$pc->findById(self::$t->getId()));

		$this->assertTrue(empty(self::$pc->findByIds("null")));
		$this->assertEquals(3, sizeof(self::$pc->findByIds(array(self::$u->getId(), self::$u1->getId(), self::$u2->getId()))));

		$this->assertTrue(empty(self::$pc->findNearby("null", null, 100, 1, 1)));
		$l1 = self::$pc->findNearby(self::$u->getType(), "*", 10, 40.60, -73.90);
		$this->assertFalse(empty($l1));

		$this->assertTrue(empty(self::$pc->findNearby("null", null, 100, 1, 1)));
		$l1 = self::$pc->findNearby(self::$u->getType(), "*", 10, 40.60, -73.90);
		$this->assertFalse(empty($l1));

		//$this->assertTrue(empty(self::$pc->findPrefix(null, null, "")));
		$this->assertTrue(empty(self::$pc->findPrefix("", "null", "xx")));
		$this->assertFalse(empty(self::$pc->findPrefix(self::$u->getType(), "name", "Ann")));

		//$this->assertFalse(empty(self::$pc->findQuery(null, null)));
		$this->assertFalse(empty(self::$pc->findQuery("", "*")));
		$this->assertEquals(2, sizeof(self::$pc->findQuery(self::$a1->getType(), "country:US")));
		$this->assertFalse(empty(self::$pc->findQuery(self::$u->getType(), "Ann*")));
		$this->assertFalse(empty(self::$pc->findQuery(self::$u->getType(), "Ann*")));
		$this->assertTrue(sizeof(self::$pc->findQuery(null, "*")) > 4);

		$p = new Pager();
		$this->assertEquals(0, $p->count);
		$res = self::$pc->findQuery(self::$u->getType(), "*", $p);
		$this->assertEquals(sizeof($res), $p->count);
		$this->assertTrue($p->count > 0);

		$this->assertTrue(empty(self::$pc->findSimilar(self::$t->getType(), "", null, null)));
		$this->assertTrue(empty(self::$pc->findSimilar(self::$t->getType(), "", array(), "")));
		$res = self::$pc->findSimilar(self::$s1->getType(), self::$s1->getId(), array("properties.text"), self::$s1->text);
		$this->assertFalse(empty($res));
		$this->assertEquals(self::$s2->getId(), $res[0]->getId());

		$i0 = sizeof(self::$pc->findTagged(self::$u->getType(), null));
		$i1 = sizeof(self::$pc->findTagged(self::$u->getType(), array("two")));
		$i2 = sizeof(self::$pc->findTagged(self::$u->getType(), array("one", "two")));
		$i3 = sizeof(self::$pc->findTagged(self::$u->getType(), array("three")));
		$i4 = sizeof(self::$pc->findTagged(self::$u->getType(), array("four", "three")));
		$i5 = sizeof(self::$pc->findTagged(self::$u->getType(), array("five", "three")));
		$i6 = sizeof(self::$pc->findTagged(self::$t->getType(), array("four", "three")));

		$this->assertEquals(0, $i0);
		$this->assertEquals(2, $i1);
		$this->assertEquals(1, $i2);
		$this->assertEquals(3, $i3);
		$this->assertEquals(2, $i4);
		$this->assertEquals(1, $i5);
		$this->assertEquals(0, $i6);

		//$this->assertFalse(empty(self::$pc->findTags("null")));
		$this->assertFalse(empty(self::$pc->findTags("")));
		$this->assertTrue(empty(self::$pc->findTags("unknown")));
		$this->assertTrue(sizeof(self::$pc->findTags(self::$t->tag)) >= 1);

		$this->assertEquals(3, sizeof(self::$pc->findTermInList(self::$u->getType(), "id",
				array(self::$u->getId(), self::$u1->getId(), self::$u2->getId(), "xxx", "yyy"))));

		// many terms
		$terms = array();
//		$terms["type"] = self::$u->getType();
		$terms["id"] = self::$u->getId();

		$terms1 = array();
		$terms1["type"] = null;
		$terms1["id"] = " ";

		$terms2 = array();
		$terms2[" "] = "bad";
		$terms2[""] = "";

		$this->assertEquals(1, sizeof(self::$pc->findTerms(self::$u->getType(), $terms, true)));
		$this->assertTrue(empty(self::$pc->findTerms(self::$u->getType(), $terms1, true)));
		$this->assertTrue(empty(self::$pc->findTerms(self::$u->getType(), $terms2, true)));

		// single term
		$this->assertTrue(empty(self::$pc->findTerms("null")));
		$this->assertTrue(empty(self::$pc->findTerms(self::$u->getType(), array("" => null), true)));
		$this->assertTrue(empty(self::$pc->findTerms(self::$u->getType(), array("" => ""), true)));
		$this->assertTrue(empty(self::$pc->findTerms(self::$u->getType(), array("term" => null), true)));
		$this->assertTrue(sizeof(self::$pc->findTerms(self::$u->getType(), array("type" => self::$u->getType()), true)) >= 2);

		$this->assertTrue(empty(self::$pc->findWildcard(self::$u->getType(), null, null)));
		$this->assertTrue(empty(self::$pc->findWildcard(self::$u->getType(), "", "")));
		$this->assertFalse(empty(self::$pc->findWildcard(self::$u->getType(), "name", "An*")));

		$this->assertTrue(self::$pc->getCount() > 4);
		$this->assertNotEquals(0, self::$pc->getCount(""));
		$this->assertEquals(0, self::$pc->getCount("test"));
		$this->assertTrue(self::$pc->getCount(self::$u->getType()) >= 3);

		$this->assertEquals(0, self::$pc->getCount("nullnull"));
		$this->assertEquals(0, self::$pc->getCount(self::$u->getType(), array("id" => " ")));
		$this->assertEquals(1, self::$pc->getCount(self::$u->getType(), array("id" => self::$u->getId())));
		$this->assertTrue(self::$pc->getCount(null, array("type" => self::$u->getType())) > 1);
	}

	public function testLinks() {
		$this->assertNotNull(self::$pc->link(self::$u, self::$t->getId()));
		$this->assertNotNull(self::$pc->link(self::$u, self::$u2->getId()));

		$this->assertFalse(self::$pc->isLinkedToObject(self::$u, new ParaObject("unknown")));
		$this->assertTrue(self::$pc->isLinkedToObject(self::$u, self::$t));
		$this->assertTrue(self::$pc->isLinkedToObject(self::$u, self::$u2));

		sleep(1);

		$this->assertEquals(1, sizeof(self::$pc->getLinkedObjects(self::$u, "tag")));
		$this->assertEquals(1, sizeof(self::$pc->getLinkedObjects(self::$u, "sysprop")));

		$this->assertEquals(0, self::$pc->countLinks(self::$u, null));
		$this->assertEquals(1, self::$pc->countLinks(self::$u, "tag"));
		$this->assertEquals(1, self::$pc->countLinks(self::$u, "sysprop"));

		self::$pc->unlinkAll(self::$u);

		$this->assertFalse(self::$pc->isLinkedToObject(self::$u, self::$t));
		$this->assertFalse(self::$pc->isLinkedToObject(self::$u, self::$u2));
	}

	public function testUtils() {
		$id1 = self::$pc->newId();
		$id2 = self::$pc->newId();
		$this->assertNotNull($id1);
		$this->assertFalse(empty($id1));
		$this->assertNotEquals($id1, $id2);

		$ts = self::$pc->getTimestamp();
		$this->assertNotNull($ts);
		$this->assertNotEquals(0, $ts);

		$date1 = self::$pc->formatDate("MM dd yyyy", "US");
		$date2 = date("m d Y");
		$this->assertEquals($date2, $date1);

		$ns1 = self::$pc->noSpaces(" test  123		test ", "");
		$this->assertEquals("test123test", $ns1);

		$st1 = self::$pc->stripAndTrim(" %^&*( cool )		@!");
		$this->assertEquals("cool", $st1);

		$md1 = self::$pc->markdownToHtml("# hello **test**");
		$this->assertEquals("<h1>hello <strong>test</strong></h1>\n", $md1);

		$ht1 = self::$pc->approximately(15000);
		$this->assertEquals("15s", $ht1);
	}

	public function testMisc() {
		$types = self::$pc->types();
		$this->assertNotNull($types);
		$this->assertFalse(empty($types));
		$this->assertTrue(array_key_exists("users", $types));

		$this->assertEquals("app:para", self::$pc->me()->getId());
	}

	public function testValidationConstraints() {
		// Validations
		$kittenType = "kitten";
		$constraints = self::$pc->validationConstraints();
		$this->assertFalse(empty($constraints));
		$this->assertTrue(array_key_exists("app", $constraints));
		$this->assertTrue(array_key_exists("user", $constraints));

		$constraint = self::$pc->validationConstraints("app");
		$this->assertFalse(empty($constraint));
		$this->assertTrue(array_key_exists("app", $constraint));
		$this->assertEquals(1, sizeof($constraint));

		self::$pc->addValidationConstraint($kittenType, "paws", Constraint::required());
		$constraint = self::$pc->validationConstraints($kittenType);
		$this->assertTrue(array_key_exists($kittenType, $constraint));
		$this->assertTrue(array_key_exists("paws", $constraint[$kittenType]));

		$ct = new ParaObject("felix");
		$ct->setType($kittenType);
		$ct2 = null;
		try {
			// validation fails
			$ct2 = self::$pc->create($ct);
		} catch (\Exception $e) {}

		$this->assertNull($ct2);
		$ct->paws = "4";
		$this->assertNotNull(self::$pc->create($ct));

		self::$pc->removeValidationConstraint($kittenType, "paws", "required");
		$constraint = self::$pc->validationConstraints($kittenType);
		$this->assertTrue(empty($constraint));
		$this->assertFalse(array_key_exists($kittenType, $constraint));

		// votes
		$this->assertTrue(self::$pc->voteUp($ct, self::$u->getId()));
		$this->assertFalse(self::$pc->voteUp($ct, self::$u->getId()));
		$this->assertTrue(self::$pc->voteDown($ct, self::$u->getId()));
		$this->assertTrue(self::$pc->voteDown($ct, self::$u->getId()));
		$this->assertFalse(self::$pc->voteDown($ct, self::$u->getId()));

		self::$pc->delete($ct);
		self::$pc->delete(new ParaObject("vote:".self::$u->getId().":".$ct->getId(), "vote"));

		$this->assertNotNull(self::$pc->getServerVersion());
		$this->assertNotEquals("unknown", self::$pc->getServerVersion());
	}

	public function testResourcePermissions() {
		// Permissions
		$permits = self::$pcChild->resourcePermissions();
		$this->assertNotNull($permits);

		$this->assertTrue(empty(self::$pcChild->grantResourcePermission(null, self::dogsType, array())));
		$this->assertTrue(empty(self::$pcChild->grantResourcePermission(" ", "", array())));

		self::$pcChild->grantResourcePermission(self::$u1->getId(), self::dogsType, array("GET"));
		$permits = self::$pcChild->resourcePermissions(self::$u1->getId());
		$this->assertTrue(array_key_exists(self::$u1->getId(), $permits));
		$this->assertTrue(array_key_exists(self::dogsType, $permits[self::$u1->getId()]));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "GET"));
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "POST"));
		// anonymous permissions
		$this->assertFalse(self::$pcChild->isAllowedTo("*", "utils/timestamp", "GET"));
		$this->assertNotNull(self::$pcChild->grantResourcePermission("*", "utils/timestamp", array("GET"), true));
		//$this->assertTrue(self::$pc2->getTimestamp() > 0);
		$this->assertFalse(self::$pcChild->isAllowedTo("*", "utils/timestamp", "DELETE"));

		$permits = self::$pcChild->resourcePermissions();
		$this->assertTrue(array_key_exists(self::$u1->getId(), $permits));
		$this->assertTrue(array_key_exists(self::dogsType, $permits[self::$u1->getId()]));

		self::$pcChild->revokeResourcePermission(self::$u1->getId(), self::dogsType);
		$permits = self::$pcChild->resourcePermissions(self::$u1->getId());
		$this->assertFalse(array_key_exists(self::dogsType, $permits[self::$u1->getId()]));
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "GET"));
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "POST"));

		self::$pcChild->grantResourcePermission(self::$u2->getId(), "*", array("POST", "PUT", "PATCH", "DELETE"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u2->getId(), self::dogsType, "PUT"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u2->getId(), self::dogsType, "PATCH"));

		self::$pcChild->revokeAllResourcePermissions(self::$u2->getId());
		$permits = self::$pcChild->resourcePermissions();
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u2->getId(), self::dogsType, "PUT"));
		$this->assertFalse(empty($permits));
//		$this->assertTrue(empty($permits[self::$u2->getId()]));

		self::$pcChild->grantResourcePermission(self::$u1->getId(), self::dogsType, array("POST", "PUT", "PATCH", "DELETE"));
		self::$pcChild->grantResourcePermission("*", self::catsType, array("POST", "PUT", "PATCH", "DELETE"));
		self::$pcChild->grantResourcePermission("*", "*", array("GET"));
		// user-specific permissions are in effect
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "PUT"));
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "GET"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "PUT"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "GET"));

		self::$pcChild->revokeAllResourcePermissions(self::$u1->getId());
		// user-specific permissions not found so check wildcard
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "PUT"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "GET"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "PUT"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "GET"));

		self::$pcChild->revokeResourcePermission("*", self::catsType);
		// resource-specific permissions not found so check wildcard
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "PUT"));
		$this->assertFalse(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "PUT"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::dogsType, "GET"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u1->getId(), self::catsType, "GET"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u2->getId(), self::dogsType, "GET"));
		$this->assertTrue(self::$pcChild->isAllowedTo(self::$u2->getId(), self::catsType, "GET"));

		self::$pcChild->revokeAllResourcePermissions("*");
		self::$pcChild->revokeAllResourcePermissions(self::$u1->getId());

		self::$pc->invokeDelete("apps/para-test-child");
	}

	public function testAppSettings() {
		$settings = self::$pc->appSettings();
		$this->assertNotNull($settings);
		$this->assertTrue(empty($settings));

		self::$pc->addAppSetting("", null);
		self::$pc->addAppSetting(" ", " ");
		self::$pc->addAppSetting("null", " ");
		self::$pc->addAppSetting("prop1", 1);
		self::$pc->addAppSetting("prop2", true);
		self::$pc->addAppSetting("prop3", "string");

		$this->assertEquals(4, sizeof(self::$pc->appSettings()));
		$this->assertEquals(self::$pc->appSettings(), self::$pc->appSettings(""));
		$this->assertEquals(array("value" => 1), self::$pc->appSettings("prop1"));
		$this->assertEquals(array("value" => true), self::$pc->appSettings("prop2"));
		$this->assertEquals(array("value" => "string"), self::$pc->appSettings("prop3"));

		self::$pc->removeAppSetting("prop3");
		self::$pc->removeAppSetting(" ");
		self::$pc->removeAppSetting(null);
		$this->assertTrue(empty(self::$pc->appSettings("prop3")));
		$this->assertEquals(3, sizeof(self::$pc->appSettings()));
		self::$pc->removeAppSetting("prop2");
		self::$pc->removeAppSetting("prop1");
		self::$pc->setAppSettings(new \stdClass());
		$this->assertTrue(empty(self::$pc->appSettings()));
	}

	public function testAccessTokens() {
		$this->assertNull(self::$pc->getAccessToken());
		$this->assertNull(self::$pc->signIn("facebook", "test_token"));
		self::$pc->signOut();
		$this->assertFalse(self::$pc->revokeAllTokens());
	}

}

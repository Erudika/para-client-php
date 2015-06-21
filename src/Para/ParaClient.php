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
namespace Para;

//require '../../vendor/autoload.php';

use Para\ParaObject;
use Para\Pager;
use Para\Constraint;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Http\QueryAggregator\DuplicateAggregator;
use Aws\Common\Signature\SignatureV4;
use Aws\Common\Credentials\Credentials;

/**
 * PHP client for communicating with a Para API server.
 *
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class ParaClient {

	const DEFAULT_ENDPOINT = "https://paraio.com";
	const DEFAULT_PATH = "/v1/";
	const SEPARATOR = ":";

	private $apiClient;
	private $endpoint;
	private $path;
	private $accessKey;
	private $secretKey;

	public function __construct($accessKey = null, $secretKey = null) {
		$this->accessKey = $accessKey;
		$this->secretKey = $secretKey;
		$this->apiClient = new Client();
	}

	function __destruct() {
	}

	/**
	 * Sets the API endpoint URL
	 * @param endpoint URL
	 */
	public function setEndpoint($endpoint) {
		$this->endpoint = $endpoint;
	}

	/**
	 * Returns the endpoint URL
	 * @return the endpoint
	 */
	public function getEndpoint() {
		if (empty($this->endpoint)) {
			return self::DEFAULT_ENDPOINT;
		} else {
			return $this->endpoint;
		}
	}

	/**
	 * Sets the API request path
	 * @param path a new path
	 */
	public function setApiPath($path) {
		$this->path = $path;
	}

	/**
	 * Returns the API request path
	 * @return the request path without parameters
	 */
	public function getApiPath() {
		if (empty($this->path)) {
			return self::DEFAULT_PATH;
		} else {
			if ($this->path[strlen($this->path) - 1] !== '/') {
				$this->path += '/';
			}
			return $this->path;
		}
	}

	/**
	 * Returns the App for the current access key (appid).
	 * @return the App object
	 */
	public function getApp() {
		return $this->me();
	}

	private function getEntity(Response $res = null, $returnArray = true) {
		if ($res != null) {
			$code = $res->getStatusCode();
			if ($code === 200 || $code === 201 || $code === 304) {
				if ($returnArray) {
					try {
						return $res->json();
					} catch (\Exception $exc) {
						return $res->getBody(true);
					}
				} else {
					$obj = new ParaObject();
					$obj->setFields($res->json());
					return $obj;
				}
			} else if ($code !== 404 || $code !== 304 || $code !== 204) {
				$error = $res->json();
				if ($error != null && $error["code"] != null) {
					$msg = $error["message"] != null ? $error["message"] : "error";
					error_log($msg." - ".$error["code"], 0);
				}
			}
		}
		return null;
	}

	private function getFullPath($resourcePath) {
		if (!isset($resourcePath)) {
			$resourcePath = '';
		} elseif ($resourcePath[0] === '/') {
			$resourcePath = substr($resourcePath, 1);
		}
		return $this->getApiPath().$resourcePath;
	}

	private function invokeGet($resourcePath = '/', $params = array()) {
		return $this->invokeSignedRequest("GET", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, $params);
	}

	private function invokePost($resourcePath = '/', $entity = array()) {
		return $this->invokeSignedRequest("POST", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, null, empty($entity) ? null : json_encode($entity));
	}

	private function invokePut($resourcePath = '/', $entity = array()) {
		return $this->invokeSignedRequest("PUT", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, null, empty($entity) ? null : json_encode($entity));
	}

	private function invokeDelete($resourcePath = '/', $params = array()) {
		return $this->invokeSignedRequest("DELETE", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, $params);
	}

	private function invokeSignedRequest($httpMethod, $endpointURL, $reqPath,
					$headers = array(), $params = array(), $jsonEntity = null) {

		if ($this->accessKey == null || $this->secretKey == null) {
			throw new \Exception("Security credentials are invalid.");
		}
		$sig = new SignatureV4("para");
		$req = $this->apiClient->createRequest($httpMethod, $endpointURL . $reqPath);
		$req->getQuery()->setAggregator(new DuplicateAggregator());
		$req->addHeaders($headers == null ? array() : $headers);
		$truncatedParams = false;
		if ($params != null) {
			$query = $req->getQuery();
			foreach ($params as $key => $value) {
				if (is_array($value) && !empty($value)) {
					// no spec on this case, so choose first param in array
					$query->add($key, $value[0]);
					$truncatedParams = true;
				} else {
					$query->add($key, $value);
				}
			}
		}
		if ($jsonEntity != null) {
			$req->setBody($jsonEntity);
		}
		$sig->signRequest($req, new Credentials($this->accessKey, $this->secretKey));
		if ($truncatedParams) {
			$req->getQuery()->overwriteWith($params);
		}
		try {
			return $this->apiClient->send($req);
		} catch (\Exception $ex) {
			error_log($ex->getMessage());
		}
		return null;
	}

	private function pagerToParams(Pager $p = null) {
		$map = array();
		if ($p !== null) {
			$map["page"] = $p->page;
			$map["desc"] = $p->desc;
			$map["limit"] = $p->limit;
			if ($p->sortby != null) {
				$map["sort"] = $p->sortby;
			}
		}
		return $map;
	}

	private function getItemsFromList($result = array()) {
		if (!empty($result)) {
			// this isn't very efficient but there's no way to know what type of objects we're reading
			$objects = array();
			foreach ($result as $map) {
				if (!empty($map)) {
					$p = new ParaObject();
					$p->setFields($map);
					array_push($objects, $p);
				}
			}
			return $objects;
		}
		return array();
	}

	private function getItems($result, Pager $pager = null) {
		if ($result != null && array_key_exists("items", $result)) {
			if ($pager !== null && array_key_exists("totalHits", $result)) {
				$pager->count = $result["totalHits"];
			}
			return $this->getItemsFromList($result["items"]);
		}
		return array();
	}

	/////////////////////////////////////////////
	//				 PERSISTENCE
	/////////////////////////////////////////////

	/**
	 * Persists an object to the data store.
	 * @param obj the domain object
	 * @return the same object with assigned id or null if not created.
	 */
	public function create(ParaObject $obj = null) {
		if ($obj == null) {
			return null;
		}
		return $this->getEntity($this->invokePost($obj->getType(), $obj->jsonSerialize()), false);
	}

	/**
	 * Retrieves an object from the data store.
	 * @param type the type of the object
	 * @param id the id of the object
	 * @return the retrieved object or null if not found
	 */
	public function read($type = null, $id = null) {
		if ($id == null) {
			return null;
		}
		if ($type == null) {
			return $this->getEntity($this->invokeGet("_id/".$id), false);
		} else {
			return $this->getEntity($this->invokeGet($type."/".$id), false);
		}
	}

	/**
	 * Updates an object permanently.
	 * @param obj the object to update
	 * @return the updated object
	 */
	public function update(ParaObject $obj = null) {
		if ($obj == null) {
			return null;
		}
		return $this->getEntity($this->invokePut($obj->getObjectURI(), $obj->jsonSerialize()), false);
	}

	/**
	 * Deletes an object permanently.
	 * @param obj the object
	 */
	public function delete(ParaObject $obj = null) {
		if ($obj == null) {
			return;
		}
		$this->invokeDelete($obj->getObjectURI());
	}

	/**
	 * Saves multiple objects to the data store.
	 * @param objects the list of objects to save
	 * @return a list of objects
	 */
	public function createAll($objects = array()) {
		if ($objects == null || $objects[0] == null) {
			return array();
		}
		foreach ($objects as $key => $value) {
			$objects[$key] = ($value == null) ? null : $value->jsonSerialize();
		}
		return $this->getItemsFromList($this->getEntity($this->invokePost("_batch", $objects)));
	}

	/**
	 * Retrieves multiple objects from the data store.
	 * @param keys a list of object ids
	 * @return a list of objects
	 */
	public function readAll($keys = array()) {
		if ($keys == null) {
			return array();
		}
		$ids = array();
		$ids["ids"] = $keys;
		return $this->getItemsFromList($this->getEntity($this->invokeGet("_batch", $ids)));
	}

	/**
	 * Updates multiple objects.
	 * @param objects the objects to update
	 * @return a list of objects
	 */
	public function updateAll($objects = array()) {
		if ($objects == null) {
			return array();
		}
		foreach ($objects as $key => $value) {
			$objects[$key] = ($value == null) ? null : $value->jsonSerialize();
		}
		return $this->getItemsFromList($this->getEntity($this->invokePut("_batch", $objects)));
	}

	/**
	 * Deletes multiple objects.
	 * @param keys the ids of the objects to delete
	 */
	public function deleteAll($keys = array()) {
		if ($keys == null) {
			return;
		}
		$ids = array();
		$ids["ids"] = $keys;
		$this->invokeDelete("_batch", $ids);
	}

	/**
	 * Returns a list all objects found for the given type.
	 * The result is paginated so only one page of items is returned, at a time.
	 * @param type the type of objects to search for
	 * @param pager a Pager
	 * @return a list of objects
	 */
	public function listObjects($type = null, Pager $pager = null) {
		if ($type == null) {
			return array();
		}
		return $this->getItems($this->getEntity($this->invokeGet($type, $this->pagerToParams($pager))), $pager);
	}

	/////////////////////////////////////////////
	//				 SEARCH
	/////////////////////////////////////////////

	/**
	 * Simple id search.
	 * @param id the id
	 * @return the object if found or null
	 */
	public function findById($id) {
		$params = array();
		$params["id"] = $id;
		$list = $this->getItems($this->find("id", $params));
		return empty($list) ? null : $list[0];
	}

	/**
	 * Simple multi id search.
	 * @param ids a list of ids to search for
	 * @return the object if found or null
	 */
	public function findByIds($ids = array()) {
		$params = array();
		$params["ids"] = $ids;
		return $this->getItems($this->find("ids", $params));
	}

	/**
	 * Search for Address objects in a radius of X km from a given point.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param query the query string
	 * @param radius the radius of the search circle
	 * @param lat latitude
	 * @param lng longitude
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findNearby($type, $query, $radius, $lat, $lng, Pager $pager = null) {
		$params = array();
		$params["latlng"] = $lat.",".$lng;
		$params["radius"] = var_export($radius, true);
		$params["q"] = $query;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("nearby", $params), $pager);
	}

	/**
	 * Searches for objects that have a property which value starts with a given prefix.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param field the property name of an object
	 * @param prefix the prefix
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findPrefix($type, $field, $prefix, Pager $pager = null) {
		$params = array();
		$params["field"] = $field;
		$params["prefix"] = $prefix;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("prefix", $params), $pager);
	}

	/**
	 * Simple query string search. This is the basic search method.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param query the query string
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findQuery($type, $query, Pager $pager = null) {
		$params = array();
		$params["q"] = $query;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("", $params), $pager);
	}

	/**
	 * Searches for objects that have similar property values to a given text. A "find like this" query.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param filterKey exclude an object with this key from the results (optional)
	 * @param fields a list of property names
	 * @param liketext text to compare to
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findSimilar($type, $filterKey, $fields, $liketext, Pager $pager = null) {
		$params = array();
		$params["fields"] = ($fields == null) ? null : $fields;
		$params["filterid"] = $filterKey;
		$params["like"] = $liketext;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("similar", $params), $pager);
	}

	/**
	 * Searches for objects tagged with one or more tags.
	 *
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param tags the list of tags
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findTagged($type, $tags, Pager $pager = null) {
		$params = array();
		$params["tags"] = ($tags == null) ? null : $tags;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("tagged", $params), $pager);
	}

	/**
	 * Searches for Tag objects.
	 * This method might be deprecated in the future.
	 *
	 * @param keyword the tag keyword to search for
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findTags($keyword = null, Pager $pager = null) {
		$keyword = ($keyword == null) ? "*" : $keyword."*";
		return $this->findWildcard("tag", "tag", $keyword, $pager);
	}

	/**
	 * Searches for objects having a property value that is in list of possible values.
	 *
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param field the property name of an object
	 * @param terms a list of terms (property values)
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findTermInList($type, $field, $terms, Pager $pager = null) {
		$params = array();
		$params["field"] = $field;
		$params["terms"] = $terms;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("in", $params), $pager);
	}

	/**
	 * Searches for objects that have properties matching some given values. A terms query.
	 *
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param terms a map of fields (property names) to terms (property values)
	 * @param matchAll match all terms. If true - AND search, if false - OR search
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findTerms($type, $terms = array(), $matchAll = true, Pager $pager = null) {
		if ($terms == null) {
			return array();
		}
		$params = array();
		$params["matchall"] = var_export($matchAll, true);
		$list = array();
		foreach ($terms as $key => $value) {
			if ($value != null) {
				array_push($list, $key.self::SEPARATOR.$value);
			}
		}
		if (!empty($terms)) {
			$params["terms"] = $list;
		}
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("terms", $params), $pager);
	}

	/**
	 * Searches for objects that have a property with a value matching a wildcard query.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param field the property name of an object
	 * @param wildcard wildcard query string. For example "cat*".
	 * @param pager a Pager
	 * @return a list of objects found
	 */
	public function findWildcard($type, $field, $wildcard = "*", Pager $pager = null) {
		$params = array();
		$params["field"] = $field;
		$params["q"] = $wildcard;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("wildcard", $params), $pager);
	}

	/**
	 * Counts indexed objects matching a set of terms/values.
	 * @param type the type of object to search for. @see ParaObject::getType()
	 * @param terms a list of terms (property values)
	 * @return the number of results found
	 */
	public function getCount($type, $terms = array()) {
		if ($terms === null) {
			return 0;
		}
		$params = array();
		$pager = new Pager();
		if (empty($terms)) {
			$params["type"] = $type;
			$this->getItems($this->find("count", $params), $pager);
			return $pager->count;
		} else {
			$list = array();
			foreach ($terms as $key => $value) {
				if ($value != null) {
					array_push($list, $key.self::SEPARATOR.$value);
				}
			}
			if (!empty($terms)) {
				$params["terms"] = $list;
			}
			$params["type"] = $type;
			$params["count"] = "true";
			$this->getItems($this->find("terms", $params), $pager);
			return $pager->count;
		}
	}

	private function find($queryType = null, $params = array()) {
		$map = array();
		if (!empty($params)) {
			$qType = ($queryType == null) ? "" : "/".$queryType;
			return $this->getEntity($this->invokeGet("search".$qType, $params));
		} else {
			$map["items"] = array();
			$map["totalHits"] = 0;
		}
		return $map;
	}

	/////////////////////////////////////////////
	//				 LINKS
	/////////////////////////////////////////////

	/**
	 * Count the total number of links between this object and another type of object.
	 * @param type2 the other type of object
	 * @param obj the object to execute this method on
	 * @return the number of links for the given object
	 */
	public function countLinks(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return 0;
		}
		$params = array();
		$params["count"] = "true";
		$pager = new Pager();
		$url = $obj->getObjectURI()."/links/".$type2;
		$this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
		return $pager->count;
	}

	/**
	 * Returns all objects linked to the given one. Only applicable to many-to-many relationships.
	 * @param type2 type of linked objects to search for
	 * @param obj the object to execute this method on
	 * @param pager a Pager
	 * @return a list of linked objects
	 */
	public function getLinkedObjects(ParaObject $obj = null, $type2 = null, Pager $pager = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return array();
		}
		$url = $obj->getObjectURI()."/links/".$type2;
		return $this->getItems($this->getEntity($this->invokeGet($url)), $pager);
	}

	/**
	 * Checks if this object is linked to another.
	 * @param type2 the other type
	 * @param id2 the other id
	 * @param obj the object to execute this method on
	 * @return true if the two are linked
	 */
	public function isLinked(ParaObject $obj = null, $type2 = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null || $id2 == null) {
			return false;
		}
		$url = $obj->getObjectURI()."/links/".$type2."/".$id2;
		return $this->getEntity($this->invokeGet($url));
	}

	/**
	 * Checks if a given object is linked to this one.
	 * @param toObj the other object
	 * @param obj the object to execute this method on
	 * @return true if linked
	 */
	public function isLinkedToObject(ParaObject $obj = null, ParaObject $toObj = null) {
		if ($obj == null || $obj->getId() == null || $toObj == null || $toObj->getId() == null) {
			return false;
		}
		return $this->isLinked($obj, $toObj->getType(), $toObj->getId());
	}

	/**
	 *
	 * @param id2 link to the object with this id
	 * @param obj the object to execute this method on
	 * @return the id of the Linker object that is created
	 */
	public function link(ParaObject $obj = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $id2 == null) {
			return null;
		}
		$url = $obj->getObjectURI()."/links/".$id2;
		return $this->getEntity($this->invokePost($url));
	}

	/**
	 * Unlinks an object from this one.
	 * Only a link is deleted. Objects are left untouched.
	 * @param type2 the other type
	 * @param obj the object to execute this method on
	 * @param id2 the other id
	 */
	public function unlink(ParaObject $obj = null, $type2 = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null || $id2 == null) {
			return;
		}
		$url = $obj->getObjectURI()."/links/".$type2."/".$id2;
		$this->invokeDelete($url);
	}

	/**
	 * Unlinks all objects that are linked to this one.
	 * @param obj the object to execute this method on
	 * Deletes all Linker objects.
	 * Only the links are deleted. Objects are left untouched.
	 */
	public function unlinkAll(ParaObject $obj = null) {
		if ($obj == null || $obj->getId() == null) {
			return;
		}
		$url = $obj->getObjectURI()."/links";
		$this->invokeDelete($url);
	}

	/**
	 * Count the total number of child objects for this object.
	 * @param type2 the type of the other object
	 * @param obj the object to execute this method on
	 * @return the number of links
	 */
	public function countChildren(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return 0;
		}
		$params = array();
		$params["count"] = "true";
		$params["childrenonly"] = "true";
		$pager = new Pager();
		$url = $obj->getObjectURI()."/links/".$type2;
		$this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
		return $pager->count;
	}

	/**
	 * Returns all child objects linked to this object.
	 * @param type2 the type of children to look for
	 * @param field the field name to use as filter
	 * @param term the field value to use as filter
	 * @param obj the object to execute this method on
	 * @param pager a Pager
	 * @return a list of ParaObject in a one-to-many relationship with this object
	 */
	public function getChildren(ParaObject $obj = null, $type2 = null, $field = null, $term = null, Pager $pager = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return array();
		}
		$params = array();
		$params["childrenonly"] = "true";
		if ($field != null) {
			$params["field"] = $field;
		}
		if ($term != null) {
			$params["term"] = $term;
		}
		$url = $obj->getObjectURI()."/links/".$type2;
		return $this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
	}

	/**
	 * Deletes all child objects permanently.
	 * @param obj the object to execute this method on
	 * @param type2 the children's type.
	 */
	public function deleteChildren(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return;
		}
		$params = array();
		$params["childrenonly"] = "true";
		$url = $obj->getObjectURI()."/links/".$type2;
		$this->invokeDelete($url, $params);
	}


	/////////////////////////////////////////////
	//				 UTILS
	/////////////////////////////////////////////

	/**
	 * Generates a new unique id.
	 * @return a new id
	 */
	public function newId() {
		$res = $this->getEntity($this->invokeGet("utils/newid"));
		return ($res != null) ? $res : "";
	}

	/**
	 * Returns the current timestamp.
	 * @return a long number
	 */
	public function getTimestamp() {
		$res = $this->getEntity($this->invokeGet("utils/timestamp"));
		return $res != null ? $res : 0;
	}

	/**
	 * Formats a date in a specific format.
	 * @param format the date format
	 * @param loc the locale instance
	 * @return a formatted date
	 */
	public function formatDate($format = "", $loc = null) {
		$params = array("format" => $format,	"locale" => $loc);
		return $this->getEntity($this->invokeGet("utils/formatdate", $params));
	}

	/**
	 * Converts spaces to dashes.
	 * @param str a string with spaces
	 * @param replaceWith a string to replace spaces with
	 * @return a string with dashes
	 */
	public function noSpaces($str = "", $replaceWith = "") {
		$params = array("string" => $str,	"replacement" => $replaceWith);
		return $this->getEntity($this->invokeGet("utils/nospaces", $params));
	}

	/**
	 * Strips all symbols, punctuation, whitespace and control chars from a string.
	 * @param str a dirty string
	 * @return a clean string
	 */
	public function stripAndTrim($str = "") {
		$params = array("string" => $str);
		return $this->getEntity($this->invokeGet("utils/nosymbols", $params));
	}

	/**
	 * Converts Markdown to HTML
	 * @param markdown$Markdown
	 * @return HTML
	 */
	public function markdownToHtml($markdownString = "") {
		$params = array("md" => $markdownString);
		return $this->getEntity($this->invokeGet("utils/md2html", $params));
	}

	/**
	 * Returns the number of minutes, hours, months elapsed for a time delta (milliseconds).
	 * @param delta the time delta between two events, in milliseconds
	 * @return a string like "5m", "1h"
	 */
	public function approximately($delta = "") {
		$params = array("delta" => $delta);
		return $this->getEntity($this->invokeGet("utils/timeago", $params));
	}

	/////////////////////////////////////////////
	//				 MISC
	/////////////////////////////////////////////

	/**
	 * First-time setup - creates the root app and returns its credentials.
	 * @return a map of credentials
	 */
	protected function setup() {
		return $this->getEntity($this->invokeGet("_setup"));
	}

	/**
	 * Generates a new set of access/secret keys.
	 * Old keys are discarded and invalid after this.
	 * @return a map of new credentials
	 */
	public function newKeys() {
		return $this->getEntity($this->invokePost("_newkeys"));
	}

	/**
	 * Returns all registered types for this App.
	 * @return a map of plural-singular form of all the registered types.
	 */
	public function types() {
		return $this->getEntity($this->invokeGet("_types"));
	}

	/**
	 * Returns the validation constraints map.
	 * @param type a type
	 * @return a map containing all validation constraints.
	 */
	public function validationConstraints($type = "") {
		return $this->getEntity($this->invokeGet("_constraints/".$type));
	}

	/**
	 * Add a new constraint for a given field.
	 * @param type a type
	 * @param field a field name
	 * @param c the constraint
	 * @return a map containing all validation constraints for this type.
	 */
	public function addValidationConstraint($type, $field, Constraint $c) {
		return $this->getEntity($this->invokePut("_constraints/".$type."/".$field."/".$c->getName(), $c->getPayload()));
	}

	/**
	 * Removes a validation constraint for a given field.
	 * @param type a type
	 * @param field a field name
	 * @param constraintName the name of the constraint to remove
	 * @return a map containing all validation constraints for this type.
	 */
	public function removeValidationConstraint($type, $field, $constraintName) {
		return $this->getEntity($this->invokeDelete("_constraints/".$type."/".$field."/".$constraintName));
	}

	/**
	 * Returns a User or an App that is currently authenticated.
	 * @return a ParaObject
	 */
	public function me() {
		return $this->getEntity($this->invokeGet("_me"), false);
	}

}
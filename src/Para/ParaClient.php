<?php
/*
 * Copyright 2013-2021 Erudika. https://erudika.com
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

use Para\ParaObject;
use Para\Pager;
use Para\Constraint;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;

/**
 * PHP client for communicating with a Para API server.
 *
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class ParaClient {

	const DEFAULT_ENDPOINT = "https://paraio.com";
	const DEFAULT_PATH = "/v1/";
	const JWT_PATH = "/jwt_auth";
	const SEPARATOR = ":";

	private $apiClient;
	private $endpoint;
	private $path;
	private $accessKey;
	private $secretKey;
	private $tokenKey;
	private $tokenKeyExpires;
	private $tokenKeyNextRefresh;

	public function __construct($accessKey = null, $secretKey = null) {
		$this->accessKey = $accessKey;
		$this->secretKey = $secretKey;
		$this->apiClient = new Client();
	}

	function __destruct() {
	}

	/**
	 * Sets the API endpoint URL
	 * @param $endpoint endpoint URL
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
	 * @param $path a new path
	 */
	public function setApiPath($path) {
		$this->path = $path;
	}

	/**
	 * Returns the API request path
	 * @return string the request path without parameters
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
	 * @return ParaObject the App object
	 */
	public function getApp() {
		return $this->me();
	}

	/**
	 * @return the version of Para server
	 */
	public function getServerVersion() {
		$res = $this->getEntity($this->invokeGet("", null));
		if ($res == null || !isset($res["version"])) {
			return "unknown";
		} else {
			return $res["version"];
		}
	}

	/**
	 * @return string|null returns the JWT access token, or null if not signed in
	 */
	public function getAccessToken() {
		return $this->tokenKey;
	}

	/**
	 * Sets the JWT access token.
	 * @param $token a valid JWT access token
	 */
	public function setAccessToken($token = null) {
		if ($token != null) {
			try {
				$parts = explode(".", $token);
				$decoded = json_decode(base64_decode($parts[1]), true);
				if ($decoded != null && array_key_exists("exp", $decoded)) {
					$this->tokenKeyExpires = $decoded["exp"];
					$this->tokenKeyNextRefresh = $decoded["refresh"];
				}
			} catch (\Exception $ex) {
				$this->tokenKeyExpires = null;
				$this->tokenKeyNextRefresh = null;
			}
		}
		$this->tokenKey = $token;
	}

	/**
	 * Clears the JWT token from memory, if such exists.
	 */
	private function clearAccessToken() {
		$this->tokenKey = null;
		$this->tokenKeyExpires = null;
		$this->tokenKeyNextRefresh = null;
	}

	/**
	 * Deserializes a Response object to POJO of some type.
	 * @param Response $res response
	 * @param bool $returnArray true if an array should be returned
	 * @return ParaObject an object
	 */
	public function getEntity(Response $res = null, $returnArray = true) {
		if ($res != null) {
			$code = $res->getStatusCode();
			if ($code === 200 || $code === 201 || $code === 304) {
				$body = $res->getBody()->getContents();
				if ($returnArray) {
					try {
						if ($body === "{}" || $body === "{ }") {
							return array();
						}
						$json = json_decode($body, true);
						return ($json != null) ? $json : $body;
					} catch (\Exception $exc) {
						return $body;
					}
				} else {
					$obj = new ParaObject();
					$obj->setFields(json_decode($body, true));
					return $obj;
				}
			} else if ($code !== 404 || $code !== 304 || $code !== 204) {
				$error = json_decode($res->getBody()->getContents(), true);
				if ($error != null && $error["code"] != null) {
					$msg = $error["message"] != null ? $error["message"] : "error";
					error_log($msg." - ".$error["code"], 0);
				} else {
					error_log($code." - ".$res->getReasonPhrase(), 0);
				}
			}
		}
		return null;
	}

	/**
	 * @param string $resourcePath API subpath
	 * @return string the full resource path, e.g. "/v1/path"
	 */
	protected function getFullPath($resourcePath) {
		if (isset($resourcePath) && strncmp($resourcePath, self::JWT_PATH, strlen(self::JWT_PATH)) == 0) {
			return $resourcePath;
		}
		if (!isset($resourcePath) || $resourcePath == null || empty($resourcePath)) {
			$resourcePath = '';
		} elseif ($resourcePath[0] === '/') {
			$resourcePath = substr($resourcePath, 1);
		}
		return $this->getApiPath().$resourcePath;
	}

	/**
	 * Invoke a GET request to the Para API.
	 * @param string $resourcePath the subpath after '/v1/', should not start with '/'
	 * @param array $params query parameters
	 * @return Response response object
	 */
	public function invokeGet($resourcePath = '/', $params = array()) {
		return $this->invokeSignedRequest("GET", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, $params);
	}

	/**
	 * Invoke a POST request to the Para API.
	 * @param string $resourcePath the subpath after '/v1/', should not start with '/'
	 * @param array $entity request body
	 * @return Response response object
	 */
	public function invokePost($resourcePath = '/', $entity = array()) {
		return $this->invokeSignedRequest("POST", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, null, $entity == null ? null : json_encode($entity));
	}

	/**
	 * Invoke a PUT request to the Para API.
	 * @param string $resourcePath the subpath after '/v1/', should not start with '/'
	 * @param array $entity request body
	 * @return Response response object
	 */
	public function invokePut($resourcePath = '/', $entity = array()) {
		return $this->invokeSignedRequest("PUT", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, null, $entity == null ? null : json_encode($entity));
	}

	/**
	 * Invoke a PATCH request to the Para API.
	 * @param string $resourcePath the subpath after '/v1/', should not start with '/'
	 * @param array $entity request body
	 * @return Response response object
	 */
	public function invokePatch($resourcePath = '/', $entity = array()) {
		return $this->invokeSignedRequest("PATCH", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, null, $entity == null ? null : json_encode($entity));
	}

	/**
	 * Invoke a DELETE request to the Para API.
	 * @param string $resourcePath the subpath after '/v1/', should not start with '/'
	 * @param array $params query parameters
	 * @return Response response object
	 */
	public function invokeDelete($resourcePath = '/', $params = array()) {
		return $this->invokeSignedRequest("DELETE", $this->getEndpoint(),
						$this->getFullPath($resourcePath), null, $params);
	}

	protected function invokeSignedRequest($httpMethod, $endpointURL, $reqPath,
					$headers = array(), $params = array(), $jsonEntity = null) {

		if (empty($this->accessKey)) {
			trigger_error("Blank access key: ".$httpMethod." ".$reqPath, E_USER_WARNING);
			return null;
		}
		$doSign = ($this->tokenKey == null);
		if (empty($this->secretKey) && empty($this->tokenKey)) {
			if ($headers == null) {
				$headers = array();
			}
			$headers["Authorization"] = "Anonymous ".$this->accessKey;
			$doSign = false;
		}

		$headers = ($headers == null) ? array() : $headers;
		$query = array();
		if ($params != null) {
			foreach ($params as $key => $value) {
				if (is_array($value) && !empty($value)) {
					// no spec on this case, so choose first param in array
					$query[$key] = $value[0];
				} else {
					$query[$key] = $value;
				}
			}
		}
		if ($this->tokenKey != null) {
			// make sure you don't create an infinite loop!
			if (!($httpMethod == "GET" && $reqPath == self::JWT_PATH)) {
				$this->refreshToken();
			}
			$headers["Authorization"] = "Bearer ".$this->tokenKey;
		}
		$headers["User-Agent"] = "Para client for PHP";
		$headers["Content-Type"] = "application/json";
		// only sign some of the query parameters
		$queryString = empty($query) ? "" : "?" . \GuzzleHttp\Psr7\build_query($query);
		$req = new Request($httpMethod, $endpointURL . $reqPath . $queryString, $headers, $jsonEntity);

		if ($doSign) {
			$sig = new SignatureV4("para", "us-east-1");
			$req = $sig->signRequest($req, new Credentials($this->accessKey, $this->secretKey));
		}
		// send all query parameters to the server
		$queryString = ($params == null) ? "" : \GuzzleHttp\Psr7\build_query($params);
		try {
			return $this->apiClient->send($req, array(RequestOptions::QUERY => $queryString));
		} catch (\Exception $ex) {
			error_log($ex->getMessage(), 0);
		}
		return null;
	}

	/**
	 * Converts a {@link Pager} object to query parameters.
	 * @param Pager $p a pager
	 * @return array a list of query parameters
	 */
	public function pagerToParams(Pager $p = null) {
		$map = array();
		if ($p !== null) {
			$map["page"] = $p->page;
			$map["desc"] = $p->desc;
			$map["limit"] = $p->limit;
			if ($p->lastKey != null) {
				$map["lastKey"] = $p->lastKey;
			}
			if ($p->sortby != null) {
				$map["sort"] = $p->sortby;
			}
            if ($p->select != null && !empty($p->select)) {
				$map["select"] = $p->select;
			}
		}
		return $map;
	}

	/**
	 * Deserializes ParaObjects from a JSON array (the "items:[]" field in search results).
	 * @param array $result a list of deserialized arrays
	 * @return array a list of ParaObjects
	 */
	public function getItemsFromList($result = array()) {
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

	/**
	 * Converts a list of Maps to a List of ParaObjects, at a given path within the JSON tree structure.
	 * @param array $result the response body for an API request
	 * @param string $at the path (field) where the array of objects is located (defaults to 'items')
	 * @param Pager $pager a pager
	 * @return array a list of ParaObjects
	 */
	public function getItemsAt($result, $at = "items", Pager $pager = null) {
		if ($result != null && $at != null && array_key_exists($at, $result)) {
			if ($pager !== null && array_key_exists("totalHits", $result)) {
				$pager->count = $result["totalHits"];
			}
			if ($pager !== null && array_key_exists("lastKey", $result)) {
				$pager->lastKey = $result["lastKey"];
			}
			if (gettype($result) === "object") {
				return $this->getItemsFromList($result->items);
			} else {
				return $this->getItemsFromList($result[$at]);
			}
		}
		return array();
	}

	private function getItems($result, Pager $pager = null) {
		return $this->getItemsAt($result, "items", $pager);
	}

	/////////////////////////////////////////////
	//				 PERSISTENCE
	/////////////////////////////////////////////

	/**
	 * Persists an object to the data store. If the object's type and id are given,
	 * then the request will be a {@code PUT} request and any existing object will be
	 * overwritten.
	 * @param $obj the domain object
	 * @return ParaObject|null the same object with assigned id or null if not created.
	 */
	public function create(ParaObject $obj = null) {
		if ($obj == null) {
			return null;
		}
		if ($obj->getId() == null || $obj->getType() == null) {
			return $this->getEntity($this->invokePost(urlencode($obj->getType()), $obj->jsonSerialize()), false);
		} else {
			return $this->getEntity($this->invokePut($obj->getObjectURI(), $obj->jsonSerialize()), false);
		}
	}

	/**
	 * Retrieves an object from the data store.
	 * @param $type the type of the object
	 * @param $id the id of the object
	 * @return ParaObject|null the retrieved object or null if not found
	 */
	public function read($type = null, $id = null) {
		if ($id == null) {
			return null;
		}
		if ($type == null) {
			return $this->getEntity($this->invokeGet("_id/".urlencode($id)), false);
		} else {
			return $this->getEntity($this->invokeGet(urlencode($type)."/".urlencode($id)), false);
		}
	}

	/**
	 * Updates an object permanently. Supports partial updates.
	 * @param $obj the object to update
	 * @return the updated object
	 */
	public function update(ParaObject $obj = null) {
		if ($obj == null) {
			return null;
		}
		return $this->getEntity($this->invokePatch($obj->getObjectURI(), $obj->jsonSerialize()), false);
	}

	/**
	 * Deletes an object permanently.
	 * @param $obj the object
	 */
	public function delete(ParaObject $obj = null) {
		if ($obj == null) {
			return;
		}
		$this->invokeDelete($obj->getObjectURI());
	}

	/**
	 * Saves multiple objects to the data store.
	 * @param $objects the list of objects to save
	 * @return array a list of objects
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
	 * @param $keys a list of object ids
	 * @return array a list of objects
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
	 * @param $objects the objects to update
	 * @return array a list of objects
	 */
	public function updateAll($objects = array()) {
		if ($objects == null) {
			return array();
		}
		foreach ($objects as $key => $value) {
			$objects[$key] = ($value == null) ? null : $value->jsonSerialize();
		}
		return $this->getItemsFromList($this->getEntity($this->invokePatch("_batch", $objects)));
	}

	/**
	 * Deletes multiple objects.
	 * @param $keys the ids of the objects to delete
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
	 * @param $type the type of objects to search for
	 * @param $pager a Pager
	 * @return array a list of objects
	 */
	public function listObjects($type = null, Pager $pager = null) {
		if ($type == null) {
			return array();
		}
		return $this->getItems($this->getEntity($this->invokeGet(urlencode($type), $this->pagerToParams($pager))), $pager);
	}

	/////////////////////////////////////////////
	//				 SEARCH
	/////////////////////////////////////////////

	/**
	 * Simple id search.
	 * @param $id the id
	 * @return ParaObject|null the object if found or null
	 */
	public function findById($id) {
		$params = array();
		$params["id"] = $id;
		$list = $this->getItems($this->find("id", $params));
		return empty($list) ? null : $list[0];
	}

	/**
	 * Simple multi id search.
	 * @param $ids a list of ids to search for
	 * @return ParaObject|null the object if found or null
	 */
	public function findByIds($ids = array()) {
		$params = array();
		$params["ids"] = $ids;
		return $this->getItems($this->find("ids", $params));
	}

	/**
	 * Search for Address objects in a radius of X km from a given point.
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $query the query string
	 * @param $radius the radius of the search circle
	 * @param $lat latitude
	 * @param $lng longitude
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $field the property name of an object
	 * @param $prefix the prefix
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $query the query string
	 * @param $pager a Pager
	 * @return array a list of objects found
	 */
	public function findQuery($type, $query, Pager $pager = null) {
		$params = array();
		$params["q"] = $query;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("", $params), $pager);
	}

	/**
	 * Searches within a nested field. The objects of the given type must contain a nested field "nstd".
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $field the name of the field to target (within a nested field "nstd")
	 * @param $query the query string
	 * @param $pager a Pager
	 * @return array a list of objects found
	 */
	public function findNestedQuery($type, $field, $query, Pager $pager = null) {
		$params = array();
		$params["q"] = $query;
		$params["field"] = $field;
		$params["type"] = $type;
		array_merge($params, $this->pagerToParams($pager));
		return $this->getItems($this->find("nested", $params), $pager);
	}

	/**
	 * Searches for objects that have similar property values to a given text. A "find like this" query.
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $filterKey exclude an object with this key from the results (optional)
	 * @param $fields a list of property names
	 * @param $liketext text to compare to
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $tags the list of tags
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $keyword the tag keyword to search for
	 * @param $pager a Pager
	 * @return array a list of objects found
	 */
	public function findTags($keyword = null, Pager $pager = null) {
		$keyword = ($keyword == null) ? "*" : $keyword."*";
		return $this->findWildcard("tag", "tag", $keyword, $pager);
	}

	/**
	 * Searches for objects having a property value that is in list of possible values.
	 *
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $field the property name of an object
	 * @param $terms a list of terms (property values)
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $terms a map of fields (property names) to terms (property values)
	 * @param $matchAll match all terms. If true - AND search, if false - OR search
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $field the property name of an object
	 * @param $wildcard wildcard query string. For example "cat*".
	 * @param $pager a Pager
	 * @return array a list of objects found
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
	 * @param $type the type of object to search for. @see ParaObject::getType()
	 * @param $terms a list of terms (property values)
	 * @return int the number of results found
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
			$qType = (empty($queryType)) ? "/default" : "/".$queryType;
			if (empty($params["type"])) {
				return $this->getEntity($this->invokeGet("search".$qType, $params));
			} else {
				return $this->getEntity($this->invokeGet($params["type"]."/search".$qType, $params));
			}
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
	 * @param $obj the object to execute this method on
	 * @param $type2 the other type of object
	 * @return int the number of links for the given object
	 */
	public function countLinks(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return 0;
		}
		$params = array();
		$params["count"] = "true";
		$pager = new Pager();
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		$this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
		return $pager->count;
	}

	/**
	 * Returns all objects linked to the given one. Only applicable to many-to-many relationships.
	 * @param $obj the object to execute this method on
	 * @param $type2 type of linked objects to search for
	 * @param $pager a Pager
	 * @return array a list of linked objects
	 */
	public function getLinkedObjects(ParaObject $obj = null, $type2 = null, Pager $pager = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return array();
		}
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		return $this->getItems($this->getEntity($this->invokeGet($url, $this->pagerToParams($pager))), $pager);
	}

	/**
	 * Searches through all linked objects in many-to-many relationships.
	 * @param $obj the object to execute this method on
	 * @param $type2 type of linked objects to search for
	 * @param $field the name of the field to target (within a nested field "nstd")
	 * @param $query a query string
	 * @param $pager a Pager
	 * @return array a list of linked objects
	 */
	public function findLinkedObjects(ParaObject $obj = null, $type2 = null, $field = "name", $query = "*",
					Pager $pager = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return array();
		}
		$params = array();
		$params["field"] = $field;
		$params["q"] = $query;
		array_merge($params, $this->pagerToParams($pager));
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		return $this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
	}

	/**
	 * Checks if this object is linked to another.
	 * @param $obj the object to execute this method on
	 * @param $type2 the other type
	 * @param $id2 the other id
	 * @return bool true if the two are linked
	 */
	public function isLinked(ParaObject $obj = null, $type2 = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null || $id2 == null) {
			return false;
		}
		$url = $obj->getObjectURI()."/links/".urlencode($type2)."/".urlencode($id2);
		return $this->getEntity($this->invokeGet($url)) == "true";
	}

	/**
	 * Checks if a given object is linked to this one.
	 * @param $obj the object to execute this method on
	 * @param $toObj the other object
	 * @return bool true if linked
	 */
	public function isLinkedToObject(ParaObject $obj = null, ParaObject $toObj = null) {
		if ($obj == null || $obj->getId() == null || $toObj == null || $toObj->getId() == null) {
			return false;
		}
		return $this->isLinked($obj, $toObj->getType(), $toObj->getId());
	}

	/**
	 * Links an object to this one in a many-to-many relationship.
	 * Only a link is created. Objects are left untouched.
	 * The type of the second object is automatically determined on read.
	 * @param $obj the object to execute this method on
	 * @param $id2 link to the object with this id
	 * @return string the id of the Linker object that is created
	 */
	public function link(ParaObject $obj = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $id2 == null) {
			return null;
		}
		$url = $obj->getObjectURI()."/links/".urlencode($id2);
		return $this->getEntity($this->invokePost($url));
	}

	/**
	 * Unlinks an object from this one.
	 * Only a link is deleted. Objects are left untouched.
	 * @param $obj the object to execute this method on
	 * @param $type2 the other type
	 * @param $id2 the other id
	 */
	public function unlink(ParaObject $obj = null, $type2 = null, $id2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null || $id2 == null) {
			return;
		}
		$url = $obj->getObjectURI()."/links/".urlencode($type2)."/".urlencode($id2);
		$this->invokeDelete($url);
	}

	/**
	 * Unlinks all objects that are linked to this one.
	 * @param $obj the object to execute this method on
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
	 * @param $obj the object to execute this method on
	 * @param $type2 the type of the other object
	 * @return int the number of links
	 */
	public function countChildren(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return 0;
		}
		$params = array();
		$params["count"] = "true";
		$params["childrenonly"] = "true";
		$pager = new Pager();
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		$this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
		return $pager->count;
	}

	/**
	 * Returns all child objects linked to this object.
	 * @param $obj the object to execute this method on
	 * @param $type2 the type of children to look for
	 * @param $field the field name to use as filter
	 * @param $term the field value to use as filter
	 * @param $pager a Pager
	 * @return array a list of ParaObject in a one-to-many relationship with this object
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
		array_merge($params, $this->pagerToParams($pager));
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		return $this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
	}

	/**
	 * Search through all child objects. Only searches child objects directly
	 * connected to this parent via the {@code parentid} field.
	 * @param $obj the object to execute this method on
	 * @param $type2 the type of children to look for
	 * @param $query a query string
	 * @param $pager a Pager
	 * @return array a list of ParaObject in a one-to-many relationship with this object
	 */
	public function findChildren(ParaObject $obj = null, $type2 = null, $query = "*", Pager $pager = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return array();
		}
		$params = array();
		$params["childrenonly"] = "true";
		$params["q"] = $query;
		array_merge($params, $this->pagerToParams($pager));
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		return $this->getItems($this->getEntity($this->invokeGet($url, $params)), $pager);
	}

	/**
	 * Deletes all child objects permanently.
	 * @param $obj the object to execute this method on
	 * @param $type2 the children's type.
	 */
	public function deleteChildren(ParaObject $obj = null, $type2 = null) {
		if ($obj == null || $obj->getId() == null || $type2 == null) {
			return;
		}
		$params = array();
		$params["childrenonly"] = "true";
		$url = $obj->getObjectURI()."/links/".urlencode($type2);
		$this->invokeDelete($url, $params);
	}


	/////////////////////////////////////////////
	//				 UTILS
	/////////////////////////////////////////////

	/**
	 * Generates a new unique id.
	 * @return string a new id
	 */
	public function newId() {
		$res = $this->getEntity($this->invokeGet("utils/newid"));
		return ($res != null) ? $res : "";
	}

	/**
	 * Returns the current timestamp.
	 * @return string a long number
	 */
	public function getTimestamp() {
		$res = $this->getEntity($this->invokeGet("utils/timestamp"));
		return $res != null ? $res : 0;
	}

	/**
	 * Formats a date in a specific format.
	 * @param $format the date format
	 * @param $loc the locale instance
	 * @return string a formatted date
	 */
	public function formatDate($format = "", $loc = null) {
		$params = array("format" => $format,	"locale" => $loc);
		return $this->getEntity($this->invokeGet("utils/formatdate", $params));
	}

	/**
	 * Converts spaces to dashes.
	 * @param $str a string with spaces
	 * @param $replaceWith a string to replace spaces with
	 * @return string a string with dashes
	 */
	public function noSpaces($str = "", $replaceWith = "") {
		$params = array("string" => $str,	"replacement" => $replaceWith);
		return $this->getEntity($this->invokeGet("utils/nospaces", $params));
	}

	/**
	 * Strips all symbols, punctuation, whitespace and control chars from a string.
	 * @param $str a dirty string
	 * @return string a clean string
	 */
	public function stripAndTrim($str = "") {
		$params = array("string" => $str);
		return $this->getEntity($this->invokeGet("utils/nosymbols", $params));
	}

	/**
	 * Converts Markdown to HTML
	 * @param $markdownString
	 * @return string HTML
	 */
	public function markdownToHtml($markdownString = "") {
		$params = array("md" => $markdownString);
		return $this->getEntity($this->invokeGet("utils/md2html", $params));
	}

	/**
	 * Returns the number of minutes, hours, months elapsed for a time delta (milliseconds).
	 * @param $delta the time delta between two events, in milliseconds
	 * @return string a string like "5m", "1h"
	 */
	public function approximately($delta = "") {
		$params = array("delta" => $delta);
		return $this->getEntity($this->invokeGet("utils/timeago", $params));
	}

	/////////////////////////////////////////////
	//				 MISC
	/////////////////////////////////////////////

	/**
	 * Generates a new set of access/secret keys.
	 * Old keys are discarded and invalid after this.
	 * @return array a map of new credentials
	 */
	public function newKeys() {
		$keys = $this->getEntity($this->invokePost("_newkeys"));
		if ($keys != null && array_key_exists("secretKey", $keys)) {
			$this->secretKey = $keys["secretKey"];
		}
		return $keys;
	}

	/**
	 * Returns all registered types for this App.
	 * @return array a map of singular object type to object count.
	 */
	public function types() {
		return $this->getEntity($this->invokeGet("_types"));
	}

	/**
	 * Returns the number of objects for each existing type in this App.
	 * @return array a map of plural-singular form of all the registered types.
	 */
	public function typesCount() {
		return $this->getEntity($this->invokeGet("_types", array("count" => "true")));
	}

	/**
	 * Returns a User or an App that is currently authenticated.
	 * @param $jwt a valid JWT access token (optional)
	 * @return ParaObject User or App
	 */
	public function me($jwt = null) {
		if ($jwt == null) {
			return $this->getEntity($this->invokeGet("_me"), false);
		} else {
			if (strncmp($jwt, "Bearer", 6) != 0) {
				$jwt = "Bearer ".$jwt;
			}
			return $this->getEntity($this->invokeSignedRequest("GET", $this->getEndpoint(), $this->getFullPath("_me"),
							array("Authorization" => $jwt), null, null), false);
		}
	}

	/**
	 * Upvote an object and register the vote in DB.
	 * @param ParaObject $obj the object to receive +1 votes
	 * @param string $voterid the userid of the voter
	 * @return bool true if vote was successful
	 */
	public function voteUp(ParaObject $obj = null, $voterid = null) {
		if ($obj == null || $voterid == null) {
			return false;
		}
		return $this->getEntity($this->invokePatch($obj->getObjectURI(), array("_voteup" => $voterid))) == "true";
	}

	/**
	 * Downvote an object and register the vote in DB.
	 * @param ParaObject $obj the object to receive -1 votes
	 * @param string $voterid the userid of the voter
	 * @return bool true if vote was successful
	 */
	public function voteDown(ParaObject $obj = null, $voterid = null) {
		if ($obj == null || $voterid == null) {
			return false;
		}
		return $this->getEntity($this->invokePatch($obj->getObjectURI(), array("_votedown" => $voterid))) == "true";
	}

	/**
	 * Rebuilds the entire search index.
	 * @param string $destinationIndex an existing index as destination
	 * @return array a response object with properties "tookMillis" and "reindexed"
	 */
	public function rebuildIndex($destinationIndex = null) {
		if ($destinationIndex == null) {
			return $this->getEntity($this->invokePost("_reindex"));
		} else {
			return $this->getEntity($this->invokeSignedRequest("POST", $this->getEndpoint(), $this->getFullPath("_reindex"),
							null, array("destinationIndex" => $destinationIndex), null), true);
		}
	}

	/////////////////////////////////////////////
	//			Validation Constraints
	/////////////////////////////////////////////

	/**
	 * Returns the validation constraints map.
	 * @param $type a type
	 * @return array a map containing all validation constraints.
	 */
	public function validationConstraints($type = "") {
		return $this->getEntity($this->invokeGet("_constraints/".urlencode($type)));
	}

	/**
	 * Add a new constraint for a given field.
	 * @param $type a type
	 * @param $field a field name
	 * @param $c the constraint
	 * @return array a map containing all validation constraints for this type.
	 */
	public function addValidationConstraint($type, $field, Constraint $c) {
		if ($type == null || $field == null || $c == null) {
			return array();
		}
		return $this->getEntity($this->invokePut("_constraints/".urlencode($type)."/".$field."/".$c->getName(), $c->getPayload()));
	}

	/**
	 * Removes a validation constraint for a given field.
	 * @param $type a type
	 * @param $field a field name
	 * @param $constraintName the name of the constraint to remove
	 * @return array a map containing all validation constraints for this type.
	 */
	public function removeValidationConstraint($type, $field, $constraintName) {
		if ($type == null || $field == null || $constraintName == null) {
			return array();
		}
		return $this->getEntity($this->invokeDelete("_constraints/".urlencode($type)."/".$field."/".$constraintName));
	}

	/////////////////////////////////////////////
	//			Resource Permissions
	/////////////////////////////////////////////

	/**
	 * Returns only the permissions for a given subject (user) of the current app.
	 * If subject is not given returns the permissions for all subjects and resources for current app.
	 * @param $subjectid the subject id (user id)
	 * @return a map of subject ids to resource names to a list of allowed methods
	 */
	public function resourcePermissions($subjectid = null) {
		if ($subjectid == null) {
			return $this->getEntity($this->invokeGet("_permissions"));
		} else {
			return $this->getEntity($this->invokeGet("_permissions/".urlencode($subjectid)));
		}
	}

	/**
	 * Grants a permission to a subject that allows them to call the specified HTTP methods on a given resource.
	 * @param $subjectid subject id (user id)
	 * @param $resourcePath resource path or object type
	 * @param $permission a set of HTTP methods - GET, POST, PUT, PATCH, DELETE
	 * @param $allowGuestAccess if true - all unauthenticated requests will go through, 'false' by default.
	 * @return array a map of the permissions for this subject id
	 */
	public function grantResourcePermission($subjectid, $resourcePath, array $permission, $allowGuestAccess = false) {
		if ($subjectid == null || $resourcePath == null || $permission == null) {
			return array();
		}
		if ($allowGuestAccess && $subjectid === "*") {
			array_push($permission, "?");
		}
		$resourcePath = urlencode($resourcePath);
		return $this->getEntity($this->invokePut("_permissions/".urlencode($subjectid)."/".$resourcePath, $permission));
	}

	/**
	 * Revokes a permission for a subject, meaning they no longer will be able to access the given resource.
	 * @param $subjectid subject id (user id)
	 * @param $resourcePath resource path or object type
	 * @return array a map of the permissions for this subject id
	 */
	public function revokeResourcePermission($subjectid, $resourcePath) {
		if ($subjectid == null || $resourcePath == null) {
			return array();
		}
		$resourcePath = urlencode($resourcePath);
		return $this->getEntity($this->invokeDelete("_permissions/".urlencode($subjectid)."/".$resourcePath));
	}

	/**
	 * Revokes all permission for a subject.
	 * @param $subjectid subject id (user id)
	 * @return array a map of the permissions for this subject id
	 */
	public function revokeAllResourcePermissions($subjectid) {
		if ($subjectid == null) {
			return array();
		}
		return $this->getEntity($this->invokeDelete("_permissions/".urlencode($subjectid)));
	}

	/**
	 * Checks if a subject is allowed to call method X on resource Y.
	 * @param $subjectid subject id
	 * @param $resourcePath resource path or object type
	 * @param $httpMethod HTTP method name
	 * @return bool true if allowed
	 */
	public function isAllowedTo($subjectid, $resourcePath, $httpMethod) {
		if ($subjectid == null || $resourcePath == null || $httpMethod == null) {
			return false;
		}
		$resourcePath = urlencode($resourcePath);
		$url = "_permissions/".urlencode($subjectid)."/".$resourcePath."/".$httpMethod;
		return $this->getEntity($this->invokeGet($url)) == "true";
	}

	/////////////////////////////////////////////
	//			Resource Permissions
	/////////////////////////////////////////////

	/**
	 * Returns the value of a specific app setting (property). If $key is blank all settings are returned.
	 * @param $key a key (optional)
	 * @return array a map of app settings
	 */
	public function appSettings($key = null) {
		if (empty($key)) {
			return $this->getEntity($this->invokeGet("_settings"));
		} else {
			return $this->getEntity($this->invokeGet("_settings/".$key));
		}
	}

	/**
	 * Adds or overwrites an app-specific setting.
	 * @param $key a key
	 * @param $value a value
	 */
	public function addAppSetting($key, $value) {
		if (!empty(trim($key)) && $value != null) {
			$this->invokePut("_settings/".$key, array("value" => $value));
		}
	}

	/**
	 * Overwrites all app-specific settings.
	 * @param $settings a key-value map of properties
	 */
	public function setAppSettings($settings) {
		if ($settings != null) {
			$this->invokePut("_settings", $settings);
		}
	}

	/**
	 * Removes an app-specific setting.
	 * @param $key a key
	 */
	public function removeAppSetting($key) {
		if (!empty($key)) {
			$this->invokeDelete("_settings/".$key);
		}
	}

	/////////////////////////////////////////////
	//				Access Tokens
	/////////////////////////////////////////////

	/**
	 * Takes an identity provider access token and fetches the user data from that provider.
	 * A new User object is created if that user doesn't exist.
	 * Access tokens are returned upon successful authentication using one of the SDKs from
	 * Facebook, Google, Twitter, etc.
	 * <b>Note:</b> Twitter uses OAuth 1 and gives you a token and a token secret.
	 * <b>You must concatenate them like this: <code>{oauth_token}:{oauth_token_secret}</code> and
	 * use that as the provider access token.</b>
	 * @param $provider identity provider, e.g. 'facebook', 'google'...
	 * @param $providerToken access token from a provider like Facebook, Google, Twitter
	 * @param $rememberJWT it true the access token returned by Para will be stored locally and
	 * available through getAccessToken(). True by default.
	 * @return ParaObject|null a User object or null if something failed
	 */
	public function signIn($provider, $providerToken, $rememberJWT = true) {
		if ($provider != null && $providerToken != null) {
			$credentials = array();
			$credentials["appid"] = $this->accessKey;
			$credentials["provider"] = $provider;
			$credentials["token"] = $providerToken;
			$result = $this->getEntity($this->invokePost(self::JWT_PATH, $credentials));
			if ($result != null && array_key_exists("user", $result) && array_key_exists("jwt", $result)) {
				$jwtData = $result["jwt"];
				if ($rememberJWT) {
					$this->tokenKey = $jwtData["access_token"];
					$this->tokenKeyExpires = $jwtData["expires"];
					$this->tokenKeyNextRefresh = $jwtData["refresh"];
				}
				$obj = new ParaObject();
				$obj->setFields($result["user"]);
				return $obj;
			} else {
				$this->clearAccessToken();
			}
		}
		return null;
	}

	/**
	 * Clears the JWT access token but token is not revoked.
	 * Tokens can be revoked globally per user with revokeAllTokens().
	 */
	public function signOut() {
		$this->clearAccessToken();
	}

	/**
	 * Refreshes the JWT access token. This requires a valid existing token.
	 *	 Call signIn() first.
	 * @return bool true if token was refreshed
	 */
	protected function refreshToken() {
		$now = round(microtime(true) * 1000);
		$notExpired = $this->tokenKeyExpires != null && $this->tokenKeyExpires > $now;
		$canRefresh = $this->tokenKeyNextRefresh != null &&
				($this->tokenKeyNextRefresh < $now || $this->tokenKeyNextRefresh > $this->tokenKeyExpires);
		// token present and NOT expired
		if ($this->tokenKey != null && $notExpired && $canRefresh) {
			$result = $this->getEntity($this->invokeGet(self::JWT_PATH));
			if ($result != null && array_key_exists("user", $result) && array_key_exists("jwt", $result)) {
				$jwtData = $result["jwt"];
				$this->tokenKey = $jwtData["access_token"];
				$this->tokenKeyExpires = $jwtData["expires"];
				$this->tokenKeyNextRefresh = $jwtData["refresh"];
				return true;
			} else {
				$this->clearAccessToken();
			}
		}
		return false;
	}

	/**
	 * Revokes all user tokens for a given user id.
	 * This would be equivalent to "logout everywhere".
	 * <b>Note:</b> Generating a new API secret on the server will also invalidate all client tokens.
	 * Requires a valid existing token.
	 * @return bool true if successful
	 */
	public function revokeAllTokens() {
		return $this->getEntity($this->invokeDelete(self::JWT_PATH)) != null;
	}

}

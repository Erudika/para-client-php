<?php

/*
 * Copyright 2013-2022 Erudika. https://erudika.com
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

/**
 * A basic object for storing data - ParaObject.
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class ParaObject {

	private $id;
	private $timestamp;
	private $type = "sysprop";
	private $appid;
	private $parentid;
	private $creatorid;
	private $updated;
	private $name = "ParaObject";
	private $tags = array();
	private $votes = 0;
	private $version = 0;
	private $stored = true;
	private $indexed = true;
	private $cached = true;

	public function __construct($id = null, $type = "sysprop") {
		$this->id = $id;
		$this->type = $type;
		$this->timestamp = round(microtime(true) * 1000);
	}

	/**
	 * The id of an object. Usually an autogenerated unique string of numbers.
	 *
	 * @return the id
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Sets a new id. Must not be null or empty.
	 *
	 * @param $id $id the new id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * The name of the object. Can be anything.
	 *
	 * @return the name. default: [type id]
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Sets a new name. Must not be null or empty.
	 *
	 * @param $name $name the new name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * The application name. Added to support multiple separate apps.
	 * Every object must belong to an app.
	 *
	 * @return the app id (name). default: para
	 */
	public function getAppid() {
		return $this->appid;
	}

	/**
	 * Sets a new app name. Must not be null or empty.
	 *
	 * @param $appid $appid the new app id (name)
	 */
	public function setAppid($appid) {
		$this->appid = $appid;
	}

	/**
	 * The id of the parent object.
	 *
	 * @return the id of the parent or null
	 */
	public function getParentid() {
		return $this->parentid;
	}

	/**
	 * Sets a new parent id. Must not be null or empty.
	 *
	 * @param $parentid $parentid a new id
	 */
	public function setParentid($parentid) {
		$this->parentid = $parentid;
	}

	/**
	 * The name of the object's class. This is equivalent to {@link Class#getSimpleName()}.toLowerCase().
	 *
	 * @return the simple name of the class
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Sets a new object type. Must not be null or empty.
	 *
	 * @param $type $type a new type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * The id of the user who created this. Should point to a {@link User} id.
	 *
	 * @return the id or null
	 */
	public function getCreatorid() {
		return $this->creatorid;
	}

	/**
	 * Sets a new creator id. Must not be null or empty.
	 *
	 * @param $creatorid $creatorid a new id
	 */
	public function setCreatorid($creatorid) {
		$this->creatorid = $creatorid;
	}

	/**
	 * The URI of this object. For example: /users/123.
	 *
	 * @return the URI
	 */
	public function getObjectURI() {
		$def = "/".urlencode($this->getType());
		return ($this->id != null) ? $def."/".urlencode($this->id) : $def;
	}

	/**
	 * The time when the object was created, in milliseconds.
	 *
	 * @return the timestamp of creation
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

	/**
	 * Sets the timestamp.
	 *
	 * @param $timestamp $timestamp a new timestamp in milliseconds.
	 */
	public function setTimestamp($timestamp) {
		$this->timestamp = $timestamp;
	}

	/**
	 * The last time this object was updated. Timestamp in ms.
	 *
	 * @return timestamp in milliseconds
	 */
	public function getUpdated() {
		return $this->updated;
	}

	/**
	 * Sets the last updated timestamp.
	 *
	 * @param $updated updated a new timestamp
	 */
	public function setUpdated($updated) {
		$this->updated = $updated;
	}

	/**
	 * The tags associated with this object. Tags must not be null or empty.
	 *
	 * @return a set of tags, or an empty set
	 */
	public function getTags() {
		return $this->tags;
	}

	/**
	 * Merges the given tags with existing tags.
	 *
	 * @param $tags the additional tags, or clears all tags if set to null
	 */
	public function setTags($tags = array()) {
		$this->tags = $tags;
	}

	/**
	 * The votes associated with this object.
	 *
	 * @return a votes or 0
	 */
	public function getVotes() {
		return $this->votes;
	}

	/**
	 * Sets the votes.
	 *
	 * @param $votes
	 */
	public function setVotes($votes = 0) {
		$this->votes = $votes;
	}

	/**
	 * @return version number
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * @param $version version
	 */
	public function setVersion($version) {
		$this->version = $version;
	}

	/**
	 * Boolean flag which controls whether this object is stored
	 * in the database or not. Default is true.
	 *
	 * @return true if object is stored
	 */
	public function getStored() {
		return $this->stored;
	}

	/**
	 * Sets the "isStored" flag.
	 *
	 * @param $isStored when set to true, object is stored in DB.
	 */
	public function setStored($isStored = true) {
		$this->stored = $isStored;
	}

	/**
	 * Boolean flat which controls whether this object is indexed
	 * by the search engine. Default is true.
	 *
	 * @return true if this object is indexed
	 */
	public function getIndexed() {
		return $this->indexed;
	}

	/**
	 * Sets the "isIndexed" flag.
	 *
	 * @param $isIndexed when set to true, object is indexed.
	 */
	public function setIndexed($isIndexed = true) {
		$this->indexed = $isIndexed;
	}

	/**
	 * Boolean flat which controls whether this object is cached.
	 * Default is true.
	 *
	 * @return true if this object is cached on update() and create().
	 */
	public function getCached() {
		return $this->cached;
	}

	/**
	 * Sets the "isCached" flag.
	 *
	 * @param $isCached when set to true, object is cached.
	 */
	public function setCached($isCached = true) {
		$this->cached = $isCached;
	}

	/**
	 * Returns the array representation of this object,
	 * containing all properties ($key => $value).
	 * @return an associative array
	 */
	public function jsonSerialize() {
		$getter_names = get_class_methods(get_class($this));
		$gettable_attributes = array();
		foreach ($getter_names as $key => $value) {
			if (substr($value, 0, 3) === 'get') {
				$gettable_attributes[lcfirst(substr($value, 3, strlen($value)))] = $this->$value();
			}
		}
		return array_merge($gettable_attributes, get_object_vars($this));
	}

	/**
	 * Populates this object with data from a map.
	 * @param $map $map
	 * @return \Para\ParaObject
	 */
	public function setFields($map = array()) {
		if (is_array($map)) {
			foreach ($map as $key => $value) {
				$this->$key = $value;
			}
		}
		return $this;
	}

}

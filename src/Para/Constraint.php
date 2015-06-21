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

/**
 * Represents a validation constraint.
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class Constraint {

	private $name;
	private $payload;

	function __construct($name, $payload) {
		$this->name = $name;
		$this->payload = $payload;
	}

	/**
	 * The constraint name.
	 * @return a name
	 */
	function getName() {
		return $this->name;
	}

	/**
	 * Sets the name of the constraint.
	 * @param name a name
	 */
	function setName($name) {
		$this->name = $name;
	}

	/**
	 * The payload (a map)
	 * @return a map
	 */
	function getPayload() {
		return $this->payload;
	}

	/**
	 * Sets the payload.
	 * @param payload a map
	 */
	function setPayload($payload) {
		$this->payload = $payload;
	}

	/**
	 * The 'required' constraint - marks a field as required.
	 * @return constraint
	 */
	public static function required() {
		return new Constraint("required", array("message" => "messages.required"));
	}

	/**
	 * The 'min' constraint - field must contain a number larger than or equal to min.
	 * @param min the minimum value
	 * @return constraint
	 */
	public static function min($min = 0) {
		return new Constraint("min", array(
				"value" => $min,
				"message" => "messages.min"
		));
	}

	/**
	 * The 'max' constraint - field must contain a number smaller than or equal to max.
	 * @param max the maximum value
	 * @return constraint
	 */
	public static function max($max = 0) {
		return new Constraint("max", array(
				"value" => $max,
				"message" => "messages.max"
		));
	}

	/**
	 * The 'size' constraint - field must be a String, Object or Array
	 * with a given minimum and maximum length.
	 * @param min the minimum length
	 * @param max the maximum length
	 * @return constraint
	 */
	public static function size($min = 0, $max = 0) {
		return new Constraint("size", array(
				"min" => $min,
				"max" => $max,
				"message" => "messages.size"
		));
	}

	/**
	 * The 'digits' constraint - field must be a Number or String containing digits where the
	 * number of digits in the integral part is limited by 'integer', and the
	 * number of digits for the fractional part is limited
	 * by 'fraction'.
	 * @param integer the max number of digits for the integral part
	 * @param fraction the max number of digits for the fractional part
	 * @return constraint
	 */
	public static function digits($i = 0, $f = 0) {
		return new Constraint("digits", array(
				"integer" => $i,
				"fraction" => $f,
				"message" => "messages.digits"
		));
	}

	/**
	 * The 'pattern' constraint - field must contain a value matching a regular expression.
	 * @param regex a regular expression
	 * @return constraint
	 */
	public static function pattern($regex) {
		return new Constraint("pattern", array(
				"value" => $regex,
				"message" => "messages.pattern"
		));
	}

	/**
	 * The 'email' constraint - field must contain a valid email.
	 * @return constraint
	 */
	public static function email() {
		return new Constraint("email", array("message" => "messages.email"));
	}

	/**
	 * The 'falsy' constraint - field value must not be equal to 'true'.
	 * @return constraint
	 */
	public static function falsy() {
		return new Constraint("false", array("message" => "messages.false"));
	}

	/**
	 * The 'truthy' constraint - field value must be equal to 'true'.
	 * @return constraint
	 */
	public static function truthy() {
		return new Constraint("true", array("message" => "messages.true"));
	}

	/**
	 * The 'future' constraint - field value must be a Date or a timestamp in the future.
	 * @return constraint
	 */
	public static function future() {
		return new Constraint("future", array("message" => "messages.future"));
	}

	/**
	 * The 'past' constraint - field value must be a Date or a timestamp in the past.
	 * @return constraint
	 */
	public static function past() {
		return new Constraint("past", array("message" => "messages.past"));
	}

	/**
	 * The 'url' constraint - field value must be a valid URL.
	 * @return constraint
	 */
	public static function url() {
		return new Constraint("url", array("message" => "messages.url"));
	}

}

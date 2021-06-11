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

/**
 * This class stores pagination data. It limits the results for queries in the DAO
 * and Search objects and also counts the total number of results that are returned.
 * @author Alex Bogdanovski [alex@erudika.com]
 */
class Pager {

	public $page = 1;
	public $count = 0;
	public $sortby = null;
	public $desc = true;
	public $limit = 30;
	public $name = "";
	public $lastKey = null;
    public $select = null;

	public function __construct($page = 1, $sortby = null, $desc = true, $limit = 30) {
		$this->page = $page;
		$this->count = 0;
		$this->sortby = $sortby;
		$this->desc = $desc;
		$this->limit = $limit;
	}

}

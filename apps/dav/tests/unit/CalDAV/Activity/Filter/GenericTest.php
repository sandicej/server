<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\DAV\Tests\unit\CalDAV\Activity\Filter;

use OCA\DAV\CalDAV\Activity\Filter\Calendar;
use OCA\DAV\CalDAV\Activity\Filter\Todo;
use OCP\Activity\IFilter;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class GenericTest extends TestCase {
	public static function dataFilters(): array {
		return [
			[Calendar::class],
			[Todo::class],
		];
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testImplementsInterface(string $filterClass): void {
		$filter = Server::get($filterClass);
		$this->assertInstanceOf(IFilter::class, $filter);
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testGetIdentifier(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$this->assertIsString($filter->getIdentifier());
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testGetName(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$this->assertIsString($filter->getName());
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testGetPriority(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$priority = $filter->getPriority();
		$this->assertIsInt($filter->getPriority());
		$this->assertGreaterThanOrEqual(0, $priority);
		$this->assertLessThanOrEqual(100, $priority);
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testGetIcon(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$this->assertIsString($filter->getIcon());
		$this->assertStringStartsWith('http', $filter->getIcon());
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testFilterTypes(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$this->assertIsArray($filter->filterTypes([]));
	}

	/**
	 * @dataProvider dataFilters
	 */
	public function testAllowedApps(string $filterClass): void {
		/** @var IFilter $filter */
		$filter = Server::get($filterClass);
		$this->assertIsArray($filter->allowedApps());
	}
}

<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Tests\unit\CalDAV;

use OC\KnownUser\KnownUserService;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\CalDAV\Sharing\Backend as SharingBackend;
use OCA\DAV\CalDAV\Sharing\Service;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\DAV\Sharing\SharingMapper;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\Share\IManager as ShareManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Xml\Property\Href;
use Test\TestCase;

/**
 * Class CalDavBackendTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\unit\CalDAV
 */
abstract class AbstractCalDavBackend extends TestCase {


	protected CalDavBackend $backend;
	protected Principal&MockObject $principal;
	protected IUserManager&MockObject $userManager;
	protected IGroupManager&MockObject $groupManager;
	protected IEventDispatcher&MockObject $dispatcher;
	private LoggerInterface&MockObject $logger;
	private IConfig&MockObject $config;
	private ISecureRandom $random;
	protected SharingBackend $sharingBackend;
	protected IDBConnection $db;
	public const UNIT_TEST_USER = 'principals/users/caldav-unit-test';
	public const UNIT_TEST_USER1 = 'principals/users/caldav-unit-test1';
	public const UNIT_TEST_GROUP = 'principals/groups/caldav-unit-test-group';
	public const UNIT_TEST_GROUP2 = 'principals/groups/caldav-unit-test-group2';

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->principal = $this->getMockBuilder(Principal::class)
			->setConstructorArgs([
				$this->userManager,
				$this->groupManager,
				$this->createMock(IAccountManager::class),
				$this->createMock(ShareManager::class),
				$this->createMock(IUserSession::class),
				$this->createMock(IAppManager::class),
				$this->createMock(ProxyMapper::class),
				$this->createMock(KnownUserService::class),
				$this->createMock(IConfig::class),
				$this->createMock(IFactory::class)
			])
			->onlyMethods(['getPrincipalByPath', 'getGroupMembership', 'findByUri'])
			->getMock();
		$this->principal->expects($this->any())->method('getPrincipalByPath')
			->willReturn([
				'uri' => 'principals/best-friend',
				'{DAV:}displayname' => 'User\'s displayname',
			]);
		$this->principal->expects($this->any())->method('getGroupMembership')
			->withAnyParameters()
			->willReturn([self::UNIT_TEST_GROUP, self::UNIT_TEST_GROUP2]);

		$this->db = Server::get(IDBConnection::class);
		$this->random = Server::get(ISecureRandom::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->config = $this->createMock(IConfig::class);
		$this->sharingBackend = new SharingBackend(
			$this->userManager,
			$this->groupManager,
			$this->principal,
			$this->createMock(ICacheFactory::class),
			new Service(new SharingMapper($this->db)),
			$this->logger);
		$this->backend = new CalDavBackend(
			$this->db,
			$this->principal,
			$this->userManager,
			$this->random,
			$this->logger,
			$this->dispatcher,
			$this->config,
			$this->sharingBackend,
			false,
		);

		$this->cleanUpBackend();
	}

	protected function tearDown(): void {
		$this->cleanUpBackend();
		parent::tearDown();
	}

	public function cleanUpBackend(): void {
		if (is_null($this->backend)) {
			return;
		}
		$this->principal->expects($this->any())->method('getGroupMembership')
			->withAnyParameters()
			->willReturn([self::UNIT_TEST_GROUP, self::UNIT_TEST_GROUP2]);
		$this->cleanupForPrincipal(self::UNIT_TEST_USER);
		$this->cleanupForPrincipal(self::UNIT_TEST_USER1);
	}

	private function cleanupForPrincipal($principal): void {
		$calendars = $this->backend->getCalendarsForUser($principal);
		$this->dispatcher->expects(self::any())
			->method('dispatchTyped');
		foreach ($calendars as $calendar) {
			$this->backend->deleteCalendar($calendar['id'], true);
		}
		$subscriptions = $this->backend->getSubscriptionsForUser($principal);
		foreach ($subscriptions as $subscription) {
			$this->backend->deleteSubscription($subscription['id']);
		}
	}

	protected function createTestCalendar(): int {
		$this->dispatcher->expects(self::any())
			->method('dispatchTyped');

		$this->backend->createCalendar(self::UNIT_TEST_USER, 'Example', [
			'{http://apple.com/ns/ical/}calendar-color' => '#1C4587FF'
		]);
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($calendars));
		$this->assertEquals(self::UNIT_TEST_USER, $calendars[0]['principaluri']);
		/** @var SupportedCalendarComponentSet $components */
		$components = $calendars[0]['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'];
		$this->assertEquals(['VEVENT','VTODO','VJOURNAL'], $components->getValue());
		$color = $calendars[0]['{http://apple.com/ns/ical/}calendar-color'];
		$this->assertEquals('#1C4587FF', $color);
		$this->assertEquals('Example', $calendars[0]['uri']);
		$this->assertEquals('Example', $calendars[0]['{DAV:}displayname']);
		return (int)$calendars[0]['id'];
	}

	protected function createTestSubscription() {
		$this->backend->createSubscription(self::UNIT_TEST_USER, 'Example', [
			'{http://apple.com/ns/ical/}calendar-color' => '#1C4587FF',
			'{http://calendarserver.org/ns/}source' => new Href(['foo']),
		]);
		$calendars = $this->backend->getSubscriptionsForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($calendars));
		$this->assertEquals(self::UNIT_TEST_USER, $calendars[0]['principaluri']);
		$this->assertEquals('Example', $calendars[0]['uri']);
		$calendarId = $calendars[0]['id'];

		return $calendarId;
	}

	protected function createEvent($calendarId, $start = '20130912T130000Z', $end = '20130912T140000Z') {
		$randomPart = self::getUniqueID();

		$calData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-$randomPart
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:$start
DTEND;VALUE=DATE-TIME:$end
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;
		$uri0 = $this->getUniqueID('event');

		$this->dispatcher->expects(self::atLeastOnce())
			->method('dispatchTyped');

		$this->backend->createCalendarObject($calendarId, $uri0, $calData);

		return $uri0;
	}

	protected function modifyEvent($calendarId, $objectId, $start = '20130912T130000Z', $end = '20130912T140000Z') {
		$randomPart = self::getUniqueID();

		$calData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-$randomPart
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:$start
DTEND;VALUE=DATE-TIME:$end
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->backend->updateCalendarObject($calendarId, $objectId, $calData);
	}

	protected function deleteEvent($calendarId, $objectId) {
		$this->backend->deleteCalendarObject($calendarId, $objectId);
	}

	protected function assertAcl($principal, $privilege, $acl) {
		foreach ($acl as $a) {
			if ($a['principal'] === $principal && $a['privilege'] === $privilege) {
				$this->addToAssertionCount(1);
				return;
			}
		}
		$this->fail("ACL does not contain $principal / $privilege");
	}

	protected function assertNotAcl($principal, $privilege, $acl) {
		foreach ($acl as $a) {
			if ($a['principal'] === $principal && $a['privilege'] === $privilege) {
				$this->fail("ACL contains $principal / $privilege");
				return;
			}
		}
		$this->addToAssertionCount(1);
	}

	protected function assertAccess($shouldHaveAcl, $principal, $privilege, $acl) {
		if ($shouldHaveAcl) {
			$this->assertAcl($principal, $privilege, $acl);
		} else {
			$this->assertNotAcl($principal, $privilege, $acl);
		}
	}
}

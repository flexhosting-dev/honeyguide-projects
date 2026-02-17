<?php

namespace App\Tests\Service;

use App\Entity\PendingRegistrationRequest;
use App\Entity\User;
use App\Enum\RegistrationRequestStatus;
use App\Enum\RegistrationType;
use App\Repository\PendingRegistrationRequestRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\RegistrationRequestService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RegistrationRequestServiceTest extends TestCase
{
    private function createService(string $allowedDomains = ''): RegistrationRequestService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $pendingRequestRepository = $this->createMock(PendingRegistrationRequestRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        // Mock findPendingByEmail to return null (no existing request)
        $pendingRequestRepository->method('findPendingByEmail')->willReturn(null);

        // Mock findPortalAdmins to return empty array (no admins to notify)
        $userRepository->method('findPortalAdmins')->willReturn([]);

        return new RegistrationRequestService(
            $allowedDomains,
            $entityManager,
            $pendingRequestRepository,
            $userRepository,
            $notificationService,
        );
    }

    public function testIsDomainAllowedWithEmptyRestriction(): void
    {
        $service = $this->createService('');

        // When no domains are restricted, all should be allowed
        $this->assertTrue($service->isDomainAllowed('user@example.com'));
        $this->assertTrue($service->isDomainAllowed('user@gmail.com'));
        $this->assertTrue($service->isDomainAllowed('user@company.org'));
    }

    public function testIsDomainAllowedWithSingleDomain(): void
    {
        $service = $this->createService('honeyguide.org');

        $this->assertTrue($service->isDomainAllowed('user@honeyguide.org'));
        $this->assertFalse($service->isDomainAllowed('user@gmail.com'));
        $this->assertFalse($service->isDomainAllowed('user@example.com'));
    }

    public function testIsDomainAllowedWithMultipleDomains(): void
    {
        $service = $this->createService('honeyguide.org, example.com, test.io');

        $this->assertTrue($service->isDomainAllowed('user@honeyguide.org'));
        $this->assertTrue($service->isDomainAllowed('user@example.com'));
        $this->assertTrue($service->isDomainAllowed('user@test.io'));
        $this->assertFalse($service->isDomainAllowed('user@gmail.com'));
        $this->assertFalse($service->isDomainAllowed('user@other.org'));
    }

    public function testIsDomainAllowedWithSpacesInConfig(): void
    {
        $service = $this->createService('  honeyguide.org  ,  example.com  ');

        $this->assertTrue($service->isDomainAllowed('user@honeyguide.org'));
        $this->assertTrue($service->isDomainAllowed('user@example.com'));
    }

    public function testGetAllowedDomains(): void
    {
        $service = $this->createService('honeyguide.org, example.com');

        $domains = $service->getAllowedDomains();
        $this->assertCount(2, $domains);
        $this->assertContains('honeyguide.org', $domains);
        $this->assertContains('example.com', $domains);
    }

    public function testGetAllowedDomainsWhenEmpty(): void
    {
        $service = $this->createService('');

        $domains = $service->getAllowedDomains();
        $this->assertCount(0, $domains);
    }

    public function testApproveAlreadyApprovedRequestThrowsException(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $pendingRequestRepository = $this->createMock(PendingRegistrationRequestRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        $service = new RegistrationRequestService(
            '',
            $entityManager,
            $pendingRequestRepository,
            $userRepository,
            $notificationService,
        );

        $request = new PendingRegistrationRequest();
        $request->setEmail('test@example.com');
        $request->setFirstName('Test');
        $request->setLastName('User');
        $request->setRegistrationType(RegistrationType::MANUAL);
        $request->setStatus(RegistrationRequestStatus::APPROVED);

        $reviewer = new User();
        $reviewer->setEmail('admin@example.com');
        $reviewer->setFirstName('Admin');
        $reviewer->setLastName('User');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot approve an already-approved request.');

        $service->approve($request, $reviewer);
    }

    public function testRejectAlreadyApprovedRequestThrowsException(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $pendingRequestRepository = $this->createMock(PendingRegistrationRequestRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        $service = new RegistrationRequestService(
            '',
            $entityManager,
            $pendingRequestRepository,
            $userRepository,
            $notificationService,
        );

        $request = new PendingRegistrationRequest();
        $request->setEmail('test@example.com');
        $request->setFirstName('Test');
        $request->setLastName('User');
        $request->setRegistrationType(RegistrationType::MANUAL);
        $request->setStatus(RegistrationRequestStatus::APPROVED);

        $reviewer = new User();
        $reviewer->setEmail('admin@example.com');
        $reviewer->setFirstName('Admin');
        $reviewer->setLastName('User');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot reject an already-approved request.');

        $service->reject($request, $reviewer);
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Milestone;
use App\Entity\MilestoneTarget;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\TaskChecklist;
use App\Entity\Comment;
use App\Entity\Role;
use App\Entity\Tag;
use App\Enum\ProjectStatus;
use App\Enum\MilestoneStatus;
use App\Enum\TaskStatus;
use App\Enum\TaskPriority;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var array<string, int> Track task positions per project */
    private array $projectTaskPositions = [];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Get role references
        /** @var Role $portalSuperAdmin */
        $portalSuperAdmin = $this->getReference(RoleFixtures::PORTAL_SUPER_ADMIN, Role::class);
        /** @var Role $projectManager */
        $projectManager = $this->getReference(RoleFixtures::PROJECT_MANAGER, Role::class);
        /** @var Role $projectMember */
        $projectMember = $this->getReference(RoleFixtures::PROJECT_MEMBER, Role::class);

        // Create users
        $testUser = new User();
        $testUser->setEmail('test@example.com');
        $testUser->setFirstName('Test');
        $testUser->setLastName('User');
        $testUser->setPassword($this->passwordHasher->hashPassword($testUser, 'password123'));
        $testUser->setIsVerified(true);
        $manager->persist($testUser);

        $adminUser = new User();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setFirstName('Admin');
        $adminUser->setLastName('User');
        $adminUser->setPassword($this->passwordHasher->hashPassword($adminUser, 'admin123'));
        $adminUser->setIsVerified(true);
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setPortalRole($portalSuperAdmin);
        $manager->persist($adminUser);

        $sylvester = new User();
        $sylvester->setEmail('sylvester@honeyguide.org');
        $sylvester->setFirstName('Sylvester');
        $sylvester->setLastName('Mselle');
        $sylvester->setPassword($this->passwordHasher->hashPassword($sylvester, 'password123'));
        $sylvester->setIsVerified(true);
        $manager->persist($sylvester);

        $max = new User();
        $max->setEmail('max@honeyguide.org');
        $max->setFirstName('Max');
        $max->setLastName('Msack');
        $max->setPassword($this->passwordHasher->hashPassword($max, 'password123'));
        $max->setIsVerified(true);
        $manager->persist($max);

        $fatma = new User();
        $fatma->setEmail('fatma@honeyguide.org');
        $fatma->setFirstName('Fatma');
        $fatma->setLastName('Kitine');
        $fatma->setPassword($this->passwordHasher->hashPassword($fatma, 'password123'));
        $fatma->setIsVerified(true);
        $manager->persist($fatma);

        $namnyaki = new User();
        $namnyaki->setEmail('namnyaki@honeyguide.org');
        $namnyaki->setFirstName('Namnyaki');
        $namnyaki->setLastName('Mattasia');
        $namnyaki->setPassword($this->passwordHasher->hashPassword($namnyaki, 'password123'));
        $namnyaki->setIsVerified(true);
        $manager->persist($namnyaki);

        $kateto = new User();
        $kateto->setEmail('kateto@honeyguide.org');
        $kateto->setFirstName('Kateto');
        $kateto->setLastName('Ole Kashe');
        $kateto->setPassword($this->passwordHasher->hashPassword($kateto, 'password123'));
        $kateto->setIsVerified(true);
        $manager->persist($kateto);

        $lemuta = new User();
        $lemuta->setEmail('lemuta@honeyguide.org');
        $lemuta->setFirstName('Lemuta');
        $lemuta->setLastName('Mengoru');
        $lemuta->setPassword($this->passwordHasher->hashPassword($lemuta, 'password123'));
        $lemuta->setIsVerified(true);
        $manager->persist($lemuta);

        $glad = new User();
        $glad->setEmail('glad@honeyguide.org');
        $glad->setFirstName('Glad');
        $glad->setLastName('Kampa');
        $glad->setPassword($this->passwordHasher->hashPassword($glad, 'password123'));
        $glad->setIsVerified(true);
        $manager->persist($glad);

        $daudi = new User();
        $daudi->setEmail('daudi@honeyguide.org');
        $daudi->setFirstName('Daudi');
        $daudi->setLastName('Mollel');
        $daudi->setPassword($this->passwordHasher->hashPassword($daudi, 'password123'));
        $daudi->setIsVerified(true);
        $manager->persist($daudi);

        $michael = new User();
        $michael->setEmail('michael@honeyguide.org');
        $michael->setFirstName('Michael');
        $michael->setLastName('Kambosha');
        $michael->setPassword($this->passwordHasher->hashPassword($michael, 'password123'));
        $michael->setIsVerified(true);
        $manager->persist($michael);

        $meleck = new User();
        $meleck->setEmail('meleck@honeyguide.org');
        $meleck->setFirstName('Meleck');
        $meleck->setLastName('Laizer');
        $meleck->setPassword($this->passwordHasher->hashPassword($meleck, 'password123'));
        $meleck->setIsVerified(true);
        $manager->persist($meleck);

        $sam = new User();
        $sam->setEmail('sam@honeyguide.org');
        $sam->setFirstName('Sam');
        $sam->setLastName('Shaba');
        $sam->setPassword($this->passwordHasher->hashPassword($sam, 'password123'));
        $sam->setIsVerified(true);
        $manager->persist($sam);

        // ============================================
        // Create personal projects for all users
        // ============================================
        $allUsers = [$testUser, $adminUser, $sylvester, $max, $fatma, $namnyaki, $kateto, $lemuta, $glad, $daudi, $michael, $meleck, $sam];
        foreach ($allUsers as $user) {
            $this->createPersonalProject($manager, $user, $projectManager);
        }

        // ============================================
        // PROJECT A: Southern WMAs Portfolio
        // ============================================
        $projectA = new Project();
        $projectA->setName('A. Southern WMAs Portfolio');
        $projectA->setDescription('Goal 1: Southern WMAs achieve improved governance, management, protection, HWC mitigation, livelihoods, and stakeholder engagement.');
        $projectA->setStatus(ProjectStatus::ACTIVE);
        $projectA->setOwner($adminUser);
        $projectA->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectA->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectA);

        $this->addMember($manager, $projectA, $adminUser, $projectManager);
        $this->addMember($manager, $projectA, $sylvester, $projectMember);

        // --- Milestone 1.1: Ruvuma 5 WMAs ---
        $m1_1 = new Milestone();
        $m1_1->setProject($projectA);
        $m1_1->setName('1.1 Ruvuma 5 WMAs');
        $m1_1->setDescription("Ruvuma's 5 WMAs achieve >80% Level 3 MAT performance through improved operational efficiency, strengthened AA leadership and GIA-compliant governance, effective community-led protection and HWC strategies, impactful data-driven livelihood programs, and SMART engagement efforts that enhance collaboration and pastoralist ownership.");
        $m1_1->setStatus(MilestoneStatus::OPEN);
        $m1_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m1_1);
        $this->createTarget($manager, $m1_1, 'MAT report showing management progress (>80% Level 3 by year end)', 0);
        $this->createTarget($manager, $m1_1, 'Capacited 1 Field Officer in governance, MAT, Protection, HWC monitoring', 1);
        $this->createTarget($manager, $m1_1, 'Co-implementation report on financial management capacity building in Ruvuma 5 WMAs with partners', 2);
        $this->createTarget($manager, $m1_1, 'On-demand Governance Training Reports and Periodic GIA Reports for Ruvuma 5 WMAs', 3);
        $this->createTarget($manager, $m1_1, 'Maintained Rangerpost & equipment, vehicles, reports on SOPs, anti-poaching strategy, intelligence Manual and data collection system', 4);
        $this->createTarget($manager, $m1_1, 'Reports on implemented communication strategies, stakeholder engagement strategies and awareness films', 5);
        $this->createTarget($manager, $m1_1, 'HWC toolkits training reports', 6);
        $this->createTarget($manager, $m1_1, 'Joint Livelihood initiative reports', 7);
        $this->createTarget($manager, $m1_1, '4 Meetings in each WMA with pastoralists, inclusion in AA and village committee', 8);

        $this->createTask($manager, $m1_1, '1.1.1 MAT operational efficiency', 'MAT with a focus on achieving >80% Level 3 for operational efficiency, & filling training gaps to Field Officers.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_1, '1.1.2 AA leadership & GIA governance', 'Strengthen AA leadership, decision-making and compliance so Ruvuma 5 WMAs meet mandatory GIA standards and uphold transparent, accountable participatory governance.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_1, '1.1.3 Community-led protection & HWC', 'Developing and implementing community-led natural resource protection & HWC strategies that are cost-effective, data-driven, and show clear positive results on the ground.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_1, '1.1.4 Community livelihood programs', 'Delivering cost-effective, data-driven community livelihood programs with measurable social impact.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);
        $this->createTask($manager, $m1_1, '1.1.5 SMART engagement strategies', 'Implement SMART engagement strategies to raise awareness, strengthen collaboration, and foster pastoralist WMA ownership.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);

        // --- Milestone 1.2: Liwale (Magingo WMA) ---
        $m1_2 = new Milestone();
        $m1_2->setProject($projectA);
        $m1_2->setName('1.2 Liwale (Magingo WMA)');
        $m1_2->setDescription("Integrating Governance and Management Best Practices into Magingo WMA operations to strengthen governance, management, and efficiency for developing their long-term vision to sustainability.");
        $m1_2->setStatus(MilestoneStatus::OPEN);
        $m1_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m1_2);
        $this->createTarget($manager, $m1_2, 'MAT report showing management progress >80% level 3', 0);
        $this->createTarget($manager, $m1_2, 'Capacitated Field Officer on governance, MAT, Protection, HWC, Livelihood monitoring by Dec 2026', 1);
        $this->createTarget($manager, $m1_2, 'Completed Gov. training reports at least 4 per WMA, SEGA actions development progress report', 2);
        $this->createTarget($manager, $m1_2, 'Customized SOPs and anti-poaching strategy documents, intelligence manual and data collection system. Construction of 1 Ranger Post and formal employment of 10 Rangers', 3);
        $this->createTarget($manager, $m1_2, 'Stakeholder engagement report, implemented communication strategy, and 3 awareness films', 4);

        $this->createTask($manager, $m1_2, '1.2.1 MAT operational efficiency', 'MAT aiming for >80% Level 3 in operational efficiency, & filling training gaps of Field Officers.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_2, '1.2.2 Governance interventions & GIA', 'Implement targeted governance interventions & GIA actions to provide an enabling environment for governance best practices in daily WMA operations.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_2, '1.2.3 Community-led protection', 'Implementing community-led natural resource protection strategies that are cost-effective, data-driven, and show clear positive results on the ground.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_2, '1.2.4 Stakeholder engagement & comms', 'Customize and implement SMART stakeholder engagement and communications strategies to raise awareness, and enhance collaboration and ownership of WMA initiatives.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);
        $this->createTask($manager, $m1_2, '1.2.5 SEGA Actions in Liwale', 'Implementing SEGA Actions in Liwale WMA.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);

        // --- Milestone 1.3: Ruaha WMAs ---
        $m1_3 = new Milestone();
        $m1_3->setProject($projectA);
        $m1_3->setName('1.3 Ruaha WMAs (MBOMIPA & Waga)');
        $m1_3->setDescription("Waga WMA and Mbomipa WMA have fully integrated their governance practices and professional management systems, ensuring data-driven operations and broader stakeholder engagement in decision-making.");
        $m1_3->setStatus(MilestoneStatus::OPEN);
        $m1_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m1_3);
        $this->createTarget($manager, $m1_3, 'MAT progress reports for Mbomipa and Waga', 0);
        $this->createTarget($manager, $m1_3, 'Trained Field Officer', 1);
        $this->createTarget($manager, $m1_3, 'SEGA actions reports', 2);
        $this->createTarget($manager, $m1_3, 'Carbon & other business prospects reports for Waga & MBOMIPA WMAs', 3);
        $this->createTarget($manager, $m1_3, '1 constructed RP for Waga', 4);
        $this->createTarget($manager, $m1_3, 'Reports on Protection and HWC initiatives for Waga and MBOMIPA WMAs', 5);
        $this->createTarget($manager, $m1_3, 'Livelihood initiatives reports', 6);

        $this->createTask($manager, $m1_3, '1.3.1 MAT Mbomipa & Waga', 'MAT in Mbomipa and Waga WMAs, to reach 80% MAT level 3.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_3, '1.3.2 Governance & GIA interventions', 'Implement targeted governance and GIA interventions addressing SAGE findings.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_3, '1.3.3 Alternative financing models', 'Develop alternative financing and business models to ensure WMAs\' sustainability.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);
        $this->createTask($manager, $m1_3, '1.3.4 Protection & HWC strategies', 'Exploring cost-effective community-led natural resource protection & HWC strategies that are data-driven and show clear positive results on the ground.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_3, '1.3.5 Community livelihood programs', 'Implement community-led, cost-effective, data-driven livelihood programs showing social and behavioral benefits.', TaskStatus::TODO, TaskPriority::MEDIUM, $sylvester);

        // --- Milestone 1.4: Ifinga ---
        $m1_4 = new Milestone();
        $m1_4->setProject($projectA);
        $m1_4->setName('1.4 Ifinga');
        $m1_4->setDescription("Support the establishment, feasibility assessment and initial interventions to prepare Ifinga for Honeyguide's capacity-building approach.");
        $m1_4->setStatus(MilestoneStatus::OPEN);
        $m1_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m1_4);
        $this->createTarget($manager, $m1_4, 'GMP and user right in place', 0);
        $this->createTarget($manager, $m1_4, 'Reports of initial Ifinga WMA governance and management interventions', 1);
        $this->createTarget($manager, $m1_4, 'Office space secured', 2);
        $this->createTarget($manager, $m1_4, 'Professional staff in place', 3);
        $this->createTarget($manager, $m1_4, 'Governance reports', 4);

        $this->createTask($manager, $m1_4, '1.4.1 WMA establishment support', 'Support Ifinga WMA communities and relevant stakeholders in the establishment of the WMA.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);
        $this->createTask($manager, $m1_4, '1.4.2 Basic governance & management training', 'Support WMA basic governance & management trainings.', TaskStatus::TODO, TaskPriority::HIGH, $sylvester);

        // ============================================
        // PROJECT B: Northern WMAs Portfolio
        // ============================================
        $projectB = new Project();
        $projectB->setName('B. Northern WMAs Portfolio');
        $projectB->setDescription('Goal 1: Northern WMAs achieve sustainability through improved governance, management, protection, livelihoods, and stakeholder engagement.');
        $projectB->setStatus(ProjectStatus::ACTIVE);
        $projectB->setOwner($adminUser);
        $projectB->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectB->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectB);

        $this->addMember($manager, $projectB, $adminUser, $projectManager);
        $this->addMember($manager, $projectB, $max, $projectMember);
        $this->addMember($manager, $projectB, $sam, $projectMember);

        // --- Milestone 2.1: Burunge ---
        $m2_1 = new Milestone();
        $m2_1->setProject($projectB);
        $m2_1->setName('2.1 Burunge');
        $m2_1->setDescription("Burunge WMA has a restored working relationship with Honeyguide, basic governance meetings are back on track, and conditions for deeper engagement are agreed.");
        $m2_1->setStatus(MilestoneStatus::OPEN);
        $m2_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_1);
        $this->createTarget($manager, $m2_1, 'Burunge–Honeyguide light engagement MoU / agreement', 0);
        $this->createTarget($manager, $m2_1, 'Governance meeting calendar and signed minutes', 1);
        $this->createTarget($manager, $m2_1, 'Basic governance status checklist (minimum standards restored)', 2);
        $this->createTarget($manager, $m2_1, 'Stakeholder engagement log (villages, AA, district, partners)', 3);

        $this->createTask($manager, $m2_1, '2.1.1 Re-establish Burunge relationship', 'Re-establish a constructive working relationship with Burunge WMA.', TaskStatus::TODO, TaskPriority::HIGH, $max);

        // --- Milestone 2.2: Makame ---
        $m2_2 = new Milestone();
        $m2_2->setProject($projectB);
        $m2_2->setName('2.2 Makame');
        $m2_2->setDescription("Makame maintains a ≥90% sustainability score, runs an active carbon-and-community learning hub, and has at least one additional livelihood initiative beyond health and education in place.");
        $m2_2->setStatus(MilestoneStatus::OPEN);
        $m2_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_2);
        $this->createTarget($manager, $m2_2, 'Updated sustainability scorecard (≥90%)', 0);
        $this->createTarget($manager, $m2_2, 'Revised Makame Sustainability Plan', 1);
        $this->createTarget($manager, $m2_2, 'SP26 partnership review note', 2);
        $this->createTarget($manager, $m2_2, 'Carbon and community learning curriculum pack', 3);
        $this->createTarget($manager, $m2_2, 'Learning centre improvement summary (with photos)', 4);
        $this->createTarget($manager, $m2_2, 'New livelihood initiative concept note(s)', 5);

        $this->createTask($manager, $m2_2, '2.2.1 Sustainability indicators ≥90%', 'Achieve ≥90% on Makame sustainability indicators and update the Sustainability Plan and SP26 partnership accordingly.', TaskStatus::TODO, TaskPriority::HIGH, $max);
        $this->createTask($manager, $m2_2, '2.2.2 Carbon & community learning hub', 'Strengthen Makame as a carbon-and-community learning hub by improving the curriculum and learning centre infrastructure.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);
        $this->createTask($manager, $m2_2, '2.2.3 Additional livelihood initiatives', 'Develop additional livelihood initiatives that increase Makame community benefits beyond health and education.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);

        // --- Milestone 2.3: Randilen ---
        $m2_3 = new Milestone();
        $m2_3->setProject($projectB);
        $m2_3->setName('2.3 Randilen');
        $m2_3->setDescription("Randilen reaches ≥90% on sustainability indicators, implements its tourism plan, and operates a functional photographic tourism learning hub with complementary livelihood initiatives.");
        $m2_3->setStatus(MilestoneStatus::OPEN);
        $m2_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_3);
        $this->createTarget($manager, $m2_3, 'Updated sustainability scorecard (≥90%) / human resources', 0);
        $this->createTarget($manager, $m2_3, 'Combined Sustainability Plan + SP26 partnership review', 1);
        $this->createTarget($manager, $m2_3, 'Tourism plan implementation progress report', 2);
        $this->createTarget($manager, $m2_3, 'Photographic tourism learning hub curriculum and materials', 3);
        $this->createTarget($manager, $m2_3, 'Learning centre upgrades summary (with photos)', 4);
        $this->createTarget($manager, $m2_3, 'Livelihood initiatives summary sheet (existing + new) / strategy', 5);
        $this->createTarget($manager, $m2_3, 'Pastoralist engagement summary (meetings, agreements)', 6);

        $this->createTask($manager, $m2_3, '2.3.1 Sustainability indicators ≥90%', 'Achieve ≥90% on Randilen sustainability indicators and update the Sustainability Plan and renewed partnership / focus on human resources and capacity.', TaskStatus::TODO, TaskPriority::HIGH, $max);
        $this->createTask($manager, $m2_3, '2.3.2 Photographic tourism learning hub', 'Position Randilen as a leading photographic tourism learning hub by improving curriculum, learning centre infrastructure, and implementing the tourism plan.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);
        $this->createTask($manager, $m2_3, '2.3.3 Additional livelihood initiatives', 'Develop additional livelihood initiatives that increase Randilen community benefits beyond health and education.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);

        // --- Milestone 2.4: Makao WMA ---
        $m2_4 = new Milestone();
        $m2_4->setProject($projectB);
        $m2_4->setName('2.4 Makao WMA');
        $m2_4->setDescription("The Darwin habitat and livelihood programme is completed and Makao reaches at least 80% on the sustainability indicators.");
        $m2_4->setStatus(MilestoneStatus::OPEN);
        $m2_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_4);
        $this->createTarget($manager, $m2_4, 'Darwin programme completion report (Makao section)', 0);
        $this->createTarget($manager, $m2_4, 'Updated sustainability scorecard (≥80%)', 1);
        $this->createTarget($manager, $m2_4, 'Governance and management improvement note', 2);
        $this->createTarget($manager, $m2_4, 'Financial resilience snapshot (income vs core and protection costs)', 3);
        $this->createTarget($manager, $m2_4, 'Tools/equipment handover list (HWC and protection)', 4);

        $this->createTask($manager, $m2_4, '2.4.1 Darwin programme completion', 'Finalise the Darwin-funded programme, delivering agreed habitat, governance, and livelihood improvements in Makao.', TaskStatus::TODO, TaskPriority::HIGH, $max);
        $this->createTask($manager, $m2_4, '2.4.2 Sustainability score ≥80%', 'Raise Makao\'s sustainability score to ≥80% by strengthening governance, management, and a cost-effective protection unit.', TaskStatus::TODO, TaskPriority::HIGH, $max);
        $this->createTask($manager, $m2_4, '2.4.3 Financial & community benefits plan', 'Establish a simple financial and community benefits plan that supports Makao\'s growth and resilience.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);

        // --- Milestone 2.5: Uyumbu WMA ---
        $m2_5 = new Milestone();
        $m2_5->setProject($projectB);
        $m2_5->setName('2.5 Uyumbu WMA');
        $m2_5->setDescription("Uyumbu reaches MAT ≥75% (L3), has core management manuals and policies in place, has rebuilt basic trust with communities and authorities, and has tested protection/HWC operations with a completed carbon feasibility study.");
        $m2_5->setStatus(MilestoneStatus::OPEN);
        $m2_5->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_5);
        $this->createTarget($manager, $m2_5, 'Governance and technical training completion report', 0);
        $this->createTarget($manager, $m2_5, 'Uyumbu MAT assessment (≥75% L4)', 1);
        $this->createTarget($manager, $m2_5, 'Core management manuals and policies (ops, finance, HR, patrol/HWC SOPs)', 2);
        $this->createTarget($manager, $m2_5, 'Community awareness film file/link + comms materials', 3);
        $this->createTarget($manager, $m2_5, 'Film screening and dialogue report', 4);
        $this->createTarget($manager, $m2_5, 'Protection and HWC pilot report', 5);
        $this->createTarget($manager, $m2_5, 'Carbon feasibility study', 6);
        $this->createTarget($manager, $m2_5, 'BEST', 7);

        $this->createTask($manager, $m2_5, '2.5.1 Governance to MAT ≥75% L3', 'Strengthen Uyumbu governance to MAT ≥75% L3 through targeted capacity building (technical training, learning tour) and core management manuals, guidelines, and policies.', TaskStatus::TODO, TaskPriority::HIGH, $max);
        $this->createTask($manager, $m2_5, '2.5.2 Community trust & awareness', 'Rebuild community and stakeholder trust via a short awareness film, concise communication materials, and facilitated dialogue screenings.', TaskStatus::TODO, TaskPriority::MEDIUM, $max);
        $this->createTask($manager, $m2_5, '2.5.3 Protection, HWC & carbon feasibility', 'Pilot strategic protection and human–wildlife conflict operations and complete a carbon-business feasibility assessment to secure sustainable revenue streams, including a clear BEST.', TaskStatus::TODO, TaskPriority::HIGH, $max);

        // --- Milestone 2.6: Other new WMAs ---
        $m2_6 = new Milestone();
        $m2_6->setProject($projectB);
        $m2_6->setName('2.6 Other new WMAs (UMEMARUWA, Kilolo, Chamwino)');
        $m2_6->setDescription("At least two new WMAs have basic governance structures in place, an expanded village footprint, and a short feasibility and management pack agreed with partners.");
        $m2_6->setStatus(MilestoneStatus::OPEN);
        $m2_6->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m2_6);
        $this->createTarget($manager, $m2_6, 'Governance basics starter pack (roles, templates, checklist)', 0);
        $this->createTarget($manager, $m2_6, 'Training and governance meeting log', 1);
        $this->createTarget($manager, $m2_6, 'Village mobilisation report (footprint and agreements)', 2);
        $this->createTarget($manager, $m2_6, 'Feasibility and management pack per WMA', 3);
        $this->createTarget($manager, $m2_6, 'Partner engagement summary (CWMAC, others, roles)', 4);
        $this->createTarget($manager, $m2_6, '"Readiness for scaling" checklist per WMA', 5);

        $this->createTask($manager, $m2_6, '2.6.1 Governance basics establishment', 'Establish governance basics (clarified roles, minuted decision-making meetings, short practical training) using a light-touch engagement model as time and resources allow.', TaskStatus::TODO, TaskPriority::MEDIUM, $sam);
        $this->createTask($manager, $m2_6, '2.6.2 Scalable livelihood models', 'Explore scalable livelihood models for Northern WMAs, including community banks and community training with SAWC.', TaskStatus::TODO, TaskPriority::MEDIUM, $sam);

        // ============================================
        // PROJECT C: Technical Innovations (Honeyguide Lab)
        // ============================================
        $projectC = new Project();
        $projectC->setName('C. Technical Innovations (Honeyguide Lab)');
        $projectC->setDescription('Develop and package replicable tools, frameworks, and innovations for governance, management, protection, HWC, livelihoods, and learning across WMAs.');
        $projectC->setStatus(ProjectStatus::ACTIVE);
        $projectC->setOwner($adminUser);
        $projectC->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectC->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectC);

        $this->addMember($manager, $projectC, $adminUser, $projectManager);
        $this->addMember($manager, $projectC, $fatma, $projectMember);
        $this->addMember($manager, $projectC, $namnyaki, $projectMember);
        $this->addMember($manager, $projectC, $kateto, $projectMember);
        $this->addMember($manager, $projectC, $lemuta, $projectMember);
        $this->addMember($manager, $projectC, $glad, $projectMember);

        // --- Milestone 3.1: Governance ---
        $m3_1 = new Milestone();
        $m3_1->setProject($projectC);
        $m3_1->setName('3.1 Governance');
        $m3_1->setDescription("Develop replicable governance capacity-building tools with partners, including training, monitoring frameworks, and tools to strengthen and scale community-led governance initiatives.");
        $m3_1->setStatus(MilestoneStatus::OPEN);
        $m3_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_1);
        $this->createTarget($manager, $m3_1, 'GCBF module piloted, revised, and finalized, with staff and partners trained through ToT and cascade sessions, and monitoring system in place', 0);
        $this->createTarget($manager, $m3_1, '1–2 cost-effective awareness campaigns implemented, media collaboration strengthened, community feedback collected', 1);
        $this->createTarget($manager, $m3_1, 'Rapid governance orientation and assessments for new WMA leaders conducted, all field officers trained', 2);
        $this->createTarget($manager, $m3_1, 'Stakeholder engagement approach piloted in selected WMAs, leaders and staff trained', 3);
        $this->createTarget($manager, $m3_1, 'WMA leaders trained to use the Rapid Governance Monitoring Tool, governance reviews conducted', 4);
        $this->createTarget($manager, $m3_1, 'SAGE enhanced and expanded to support additional WMAs and partner programs', 5);

        $this->createTask($manager, $m3_1, '3.1.1 Pilot & monitor GCBF Module', 'Pilot, Cascade, and Monitor the GCBF Module.', TaskStatus::TODO, TaskPriority::HIGH, $fatma);
        $this->createTask($manager, $m3_1, '3.1.2 Institutionalize governance docs & tools', 'Institutionalize and package all existing governance documents, GIA, tools, and methodologies for standardized use across WMAs.', TaskStatus::TODO, TaskPriority::HIGH, $fatma);
        $this->createTask($manager, $m3_1, '3.1.3 Rapid governance training for new leaders', 'Pilot and Support Rapid Governance Training for New WMA Leaders.', TaskStatus::TODO, TaskPriority::MEDIUM, $fatma);
        $this->createTask($manager, $m3_1, '3.1.4 Stakeholder engagement approach pilot', 'Pilot Testing and Learning from the Stakeholder Engagement & Communication Approach.', TaskStatus::TODO, TaskPriority::MEDIUM, $fatma);
        $this->createTask($manager, $m3_1, '3.1.5 Rapid Governance Monitoring Tool', 'Provide initial training and support for the WMA Rapid Governance Monitoring Tool for regular governance assessments.', TaskStatus::TODO, TaskPriority::MEDIUM, $fatma);
        $this->createTask($manager, $m3_1, '3.1.6 Enhance & scale SAGE', 'Enhance and scale SAGE for wider adoption across WMAs and partner programs beyond HGF\'s primary areas.', TaskStatus::TODO, TaskPriority::MEDIUM, $fatma);

        // --- Milestone 3.2: Management ---
        $m3_2 = new Milestone();
        $m3_2->setProject($projectC);
        $m3_2->setName('3.2 Management');
        $m3_2->setDescription("Develop and packaging the replicable tools and frameworks for professional WMA management that can be applied across diverse CBNRM contexts.");
        $m3_2->setStatus(MilestoneStatus::OPEN);
        $m3_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_2);
        $this->createTarget($manager, $m3_2, 'Standardized FCG Monitoring Framework', 0);
        $this->createTarget($manager, $m3_2, 'Pre-customized Quick Book Chart of Accounts', 1);
        $this->createTarget($manager, $m3_2, 'Board financial oversight handbook for WMAs', 2);
        $this->createTarget($manager, $m3_2, 'Packaging & publishing at least 5 additional Management Tools', 3);
        $this->createTarget($manager, $m3_2, 'Pilot leadership training program report', 4);

        $this->createTask($manager, $m3_2, '3.2.1 FCG Monitoring tool', 'Develop FCG Monitoring tool and testing.', TaskStatus::TODO, TaskPriority::HIGH, $namnyaki);
        $this->createTask($manager, $m3_2, '3.2.2 QuickBooks lite setup for WMAs', 'Develop pre-customized Quickbook lite setup file for WMAs (to build uniformity across WMAs).', TaskStatus::TODO, TaskPriority::MEDIUM, $namnyaki);
        $this->createTask($manager, $m3_2, '3.2.3 Board Financial Oversight Handbook', 'Develop WMA Board Financial Oversight Handbook + Tools (Helps governance members challenge management constructively and make informed approvals).', TaskStatus::TODO, TaskPriority::MEDIUM, $namnyaki);
        $this->createTask($manager, $m3_2, '3.2.4 WMA Management Toolbox', 'Design and consolidate a comprehensive WMA Management Toolbox and publish at least five additional tools guided by sound financial and operational management of WMAs.', TaskStatus::TODO, TaskPriority::HIGH, $namnyaki);
        $this->createTask($manager, $m3_2, '3.2.5 Leadership Training Program pilot', 'Implement a pilot of the pre-designed WMA Management Leadership Training Program across selected WMAs.', TaskStatus::TODO, TaskPriority::MEDIUM, $namnyaki);

        // --- Milestone 3.3: Protection ---
        $m3_3 = new Milestone();
        $m3_3->setProject($projectC);
        $m3_3->setName('3.3 Protection');
        $m3_3->setDescription("Review, develop, and packaging the Honeyguide's capacity-building process for the Protection of WMAs, and ensure the implementation at each of our partner sites is strategic and cost-effective.");
        $m3_3->setStatus(MilestoneStatus::OPEN);
        $m3_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_3);
        $this->createTarget($manager, $m3_3, 'HGF protection tools (SOPs, Best Practices Booklet, Engagement Strategy, Baseline Survey Template) compiled, packaged, and prepared for dissemination', 0);
        $this->createTarget($manager, $m3_3, 'Standardized Protection Tools Package developed and distributed for all WMAs', 1);
        $this->createTarget($manager, $m3_3, 'WMAs\' protection status monitored with quarterly reports', 2);
        $this->createTarget($manager, $m3_3, 'Quarterly-updated checklist of recommendation for WMA anti-poaching practices developed and shared', 3);

        $this->createTask($manager, $m3_3, '3.3.1 Package protection docs & tools', 'Institutionalize and package all existing protection documents, tools, and methodologies for standardized use across WMAs.', TaskStatus::TODO, TaskPriority::HIGH, $kateto);
        $this->createTask($manager, $m3_3, '3.3.2 Low-cost protection strategies', 'Ensure all WMAs adopt and comply with low-cost, effective protection strategies and methodologies.', TaskStatus::TODO, TaskPriority::HIGH, $kateto);
        $this->createTask($manager, $m3_3, '3.3.3 Anti-poaching tools monitoring', 'Conduct regular assessments and monitoring of anti-poaching tools to ensure full functionality and effectiveness.', TaskStatus::TODO, TaskPriority::MEDIUM, $kateto);
        $this->createTask($manager, $m3_3, '3.3.4 Anti-poaching improvement checklist', 'Develop a checklist of recommendation for anti-poaching strategic improvement.', TaskStatus::TODO, TaskPriority::MEDIUM, $kateto);

        // --- Milestone 3.4: HWC ---
        $m3_4 = new Milestone();
        $m3_4->setProject($projectC);
        $m3_4->setName('3.4 HWC');
        $m3_4->setDescription("Research, develop and packaging of replicable, innovative, low-cost yet effective solutions for communities to mitigate HWC.");
        $m3_4->setStatus(MilestoneStatus::OPEN);
        $m3_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_4);
        $this->createTarget($manager, $m3_4, 'At least 2 new innovative HEC toolkits invented', 0);
        $this->createTarget($manager, $m3_4, 'HEC scaled up and engaged in at least 2 other countries with partners', 1);
        $this->createTarget($manager, $m3_4, 'HEC methods guide compiled and packaged for use', 2);

        $this->createTask($manager, $m3_4, '3.4.1 HEC toolkit innovation', 'Drive toolkit innovation process by gathering insights through listening, creating designs, testing prototypes, validating scientifically, and scaling successful solutions.', TaskStatus::TODO, TaskPriority::HIGH, $lemuta);
        $this->createTask($manager, $m3_4, '3.4.2 HEC mitigation beyond WMAs', 'Explore HEC mitigation strategies beyond WMAs and outside the country.', TaskStatus::TODO, TaskPriority::MEDIUM, $lemuta);
        $this->createTask($manager, $m3_4, '3.4.3 Package HEC methodologies', 'Institutionalize and packaging available HEC methodologies.', TaskStatus::TODO, TaskPriority::MEDIUM, $lemuta);

        // --- Milestone 3.5: Livelihoods ---
        $m3_5 = new Milestone();
        $m3_5->setProject($projectC);
        $m3_5->setName('3.5 Livelihoods');
        $m3_5->setDescription("Develop and package Honeyguide's Education and Health Livelihoods models for replication, while expanding exploration of new income-enhancing opportunities for WMA communities.");
        $m3_5->setStatus(MilestoneStatus::OPEN);
        $m3_5->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_5);
        $this->createTarget($manager, $m3_5, 'Education and Health program Framework drafted, reviewed, designed and finalised', 0);
        $this->createTarget($manager, $m3_5, 'Implementation Reports of Kamitei Education Model for Mbomipa, Waga, and each of the Ruvuma 5 WMAs with baseline data', 1);
        $this->createTarget($manager, $m3_5, 'Pilot reports of at least one Agriculture and one Microcredit project designed and launched', 2);
        $this->createTarget($manager, $m3_5, 'Database (PDF and Excel) of 10+ livelihoods models studied and documented', 3);
        $this->createTarget($manager, $m3_5, 'Reports of at least 2 new conservation financing mechanisms developed', 4);

        $this->createTask($manager, $m3_5, '3.5.1 Education & Health replication playbook', 'Document the Makame Education and Health models into a replication playbook framework while preparing Makame WMA to fully own these programs beyond Honeyguide\'s support.', TaskStatus::TODO, TaskPriority::HIGH, $glad);
        $this->createTask($manager, $m3_5, '3.5.2 Kamitei Education replication', 'Replicate the Kamitei Education program into Mbomipa, Waga and Ruvuma 5 WMAs, ensuring WMA ownership and financial contributions.', TaskStatus::TODO, TaskPriority::HIGH, $glad);
        $this->createTask($manager, $m3_5, '3.5.3 Agriculture & microcredit pilots', 'Explore and pilot Agriculture and microcredit initiatives that can be integrated into WMA livelihood portfolios and scaled as community-owned models.', TaskStatus::TODO, TaskPriority::MEDIUM, $glad);
        $this->createTask($manager, $m3_5, '3.5.4 Livelihood programs inventory', 'Build a detailed, research-backed inventory of at least 10 livelihood-improvement programs suitable for rural WMA communities.', TaskStatus::TODO, TaskPriority::MEDIUM, $glad);
        $this->createTask($manager, $m3_5, '3.5.5 New financing models (CTFs, etc.)', 'Co-design at least 2 new financing models (CTFs, HWC insurance, BD credits etc) for WMAs.', TaskStatus::TODO, TaskPriority::MEDIUM, $glad);

        // --- Milestone 3.6: Honeyguide Learning Hub ---
        $m3_6 = new Milestone();
        $m3_6->setProject($projectC);
        $m3_6->setName('3.6 Honeyguide Learning Hub');
        $m3_6->setDescription("Establish the Honeyguide Learning Platform with a community-driven, project-based approach, featuring an online system, collaboration tools, and interactive learning activities.");
        $m3_6->setStatus(MilestoneStatus::OPEN);
        $m3_6->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m3_6);
        $this->createTarget($manager, $m3_6, 'A repository of Honeyguide lessons and courses (PDFs, videos etc)', 0);
        $this->createTarget($manager, $m3_6, 'Online self-paced learning courses', 1);
        $this->createTarget($manager, $m3_6, 'Monitoring tools to measure learning uptake and changes', 2);

        $this->createTask($manager, $m3_6, '3.6.1 Knowledge repository', 'Research and development of a repository of tools, knowledge, and information, including videos, PDFs, and Google Docs.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m3_6, '3.6.2 Online courses & monitoring', 'Design online courses and sessions for both individual and group learning, incorporating monitoring mechanisms to track uptake and learning progress.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // ============================================
        // PROJECT D: Monitoring, Evaluation & Learning
        // ============================================
        $projectD = new Project();
        $projectD->setName('D. Monitoring, Evaluation & Learning');
        $projectD->setDescription('Strengthen M&E systems, data management, GIS and mapping services aligned with SP26.');
        $projectD->setStatus(ProjectStatus::ACTIVE);
        $projectD->setOwner($adminUser);
        $projectD->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectD->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectD);

        $this->addMember($manager, $projectD, $adminUser, $projectManager);
        $this->addMember($manager, $projectD, $daudi, $projectMember);
        $this->addMember($manager, $projectD, $michael, $projectMember);

        // --- Milestone 4.1: M&E ---
        $m4_1 = new Milestone();
        $m4_1->setProject($projectD);
        $m4_1->setName('4.1 M&E');
        $m4_1->setDescription("Strengthened the Monitoring and Evaluation (M&E) system and data management framework to align with SP26, ensuring greater simplicity, accessibility, and usability.");
        $m4_1->setStatus(MilestoneStatus::OPEN);
        $m4_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m4_1);
        $this->createTarget($manager, $m4_1, 'Updated functional data tracking tools for WMA indicators of success, accessible with sustainability score', 0);
        $this->createTarget($manager, $m4_1, 'Developed Project Impact evaluation tool', 1);
        $this->createTarget($manager, $m4_1, 'Data reflecting Honeyguide\'s contribution to national strategy', 2);
        $this->createTarget($manager, $m4_1, 'Evaluation reports for SP26 strategic plan review, assessments for Northern & Southern WMAs HWC', 3);
        $this->createTarget($manager, $m4_1, 'Survey report on narrative change measuring community, stakeholder, and government perceptions', 4);
        $this->createTarget($manager, $m4_1, 'Quarterly data updated and dashboards in Google Drive and Power BI', 5);
        $this->createTarget($manager, $m4_1, 'At least one forum with WMA leaders/managers for feedback', 6);
        $this->createTarget($manager, $m4_1, 'Quarterly presentation on project progress', 7);
        $this->createTarget($manager, $m4_1, 'Quarterly consolidation of organization program reports', 8);

        $this->createTask($manager, $m4_1, '4.1.1 M&E tools & systems design', 'Design, Develop, and Implementation of M&E Tools and Systems.', TaskStatus::TODO, TaskPriority::HIGH, $daudi);
        $this->createTask($manager, $m4_1, '4.1.2 Program impacts & evaluation', 'Program Impacts and Evaluation.', TaskStatus::TODO, TaskPriority::HIGH, $daudi);
        $this->createTask($manager, $m4_1, '4.1.3 M&E capacity building', 'M&E Capacity Building for WMAs and partners (Training, Mentorship, and Coaching).', TaskStatus::TODO, TaskPriority::MEDIUM, $daudi);
        $this->createTask($manager, $m4_1, '4.1.4 Quarterly data quality & reports', 'Ensure accurate, consistent, quality data and reports quarterly.', TaskStatus::TODO, TaskPriority::HIGH, $daudi);
        $this->createTask($manager, $m4_1, '4.1.5 Ecological monitoring & evidence', 'Ecological Monitoring and Evidence Generation.', TaskStatus::TODO, TaskPriority::MEDIUM, $daudi);

        // --- Milestone 4.2: GIS and Mapping ---
        $m4_2 = new Milestone();
        $m4_2->setProject($projectD);
        $m4_2->setName('4.2 GIS and Mapping');
        $m4_2->setDescription("Enhance and streamline GIS and mapping services to produce essential maps for field operations and reporting needs.");
        $m4_2->setStatus(MilestoneStatus::OPEN);
        $m4_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m4_2);
        $this->createTarget($manager, $m4_2, 'Well organized, updated and accessible GIS data for programs use', 0);
        $this->createTarget($manager, $m4_2, 'Developed template and trained WMA managers on satellite image data analysis and vegetation index query', 1);
        $this->createTarget($manager, $m4_2, 'Developed specific WMA basemaps for reporting (incident and coverage)', 2);
        $this->createTarget($manager, $m4_2, 'Story Maps to support Honeyguide communications', 3);
        $this->createTarget($manager, $m4_2, 'Consistent, professional-quality maps support communication, M&E, and reporting', 4);

        $this->createTask($manager, $m4_2, '4.2.1 GIS maps & tools for project areas', 'Develop GIS maps and tools for all project areas to include all potential information for investment and protection.', TaskStatus::TODO, TaskPriority::HIGH, $michael);
        $this->createTask($manager, $m4_2, '4.2.2 Map making & navigation capacity', 'Establishing Capacity for Map Making and Navigation to Support Honeyguide Initiatives.', TaskStatus::TODO, TaskPriority::MEDIUM, $michael);

        // ============================================
        // PROJECT E: Special Programs
        // ============================================
        $projectE = new Project();
        $projectE->setName('E. Special Programs');
        $projectE->setDescription('K9 Unit operations and Rubondo Chimpanzee Habituation Project.');
        $projectE->setStatus(ProjectStatus::ACTIVE);
        $projectE->setOwner($adminUser);
        $projectE->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectE->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectE);

        $this->addMember($manager, $projectE, $adminUser, $projectManager);
        $this->addMember($manager, $projectE, $meleck, $projectMember);

        // --- Milestone 5.1: Honeyguide K9 Unit ---
        $m5_1 = new Milestone();
        $m5_1->setProject($projectE);
        $m5_1->setName('5.1 Honeyguide K9 Unit');
        $m5_1->setDescription("Expanding the impact and reach of Honeyguide's K9 unit for combatting wildlife crime in partnership with TANAPA, TAWA and other conservation partners.");
        $m5_1->setStatus(MilestoneStatus::OPEN);
        $m5_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m5_1);
        $this->createTarget($manager, $m5_1, 'Monthly K9 unit reports and quarterly stories', 0);
        $this->createTarget($manager, $m5_1, 'MR training center developed and approved', 1);
        $this->createTarget($manager, $m5_1, 'K9 medical plan and evacuation protocol in place with vaccination and treatment schedules', 2);

        $this->createTask($manager, $m5_1, '5.1.1 Maintain 24/7 standby K9 unit', 'Maintaining a standby canine unit that is 24/7 ready to respond to all calls in our working areas.', TaskStatus::TODO, TaskPriority::HIGH, $meleck);
        $this->createTask($manager, $m5_1, '5.1.2 Strengthen K9 operations & reporting', 'Strengthening K9 unit operations and reporting.', TaskStatus::TODO, TaskPriority::HIGH, $meleck);
        $this->createTask($manager, $m5_1, '5.1.3 HGF-Kuru-Manyara collaboration', 'Strengthen collaboration between HGF, Kuru and Manyara Board of Trustee.', TaskStatus::TODO, TaskPriority::MEDIUM, $meleck);

        // --- Milestone 5.2: Rubondo Chimpanzee Habituation ---
        $m5_2 = new Milestone();
        $m5_2->setProject($projectE);
        $m5_2->setName('5.2 Rubondo Chimpanzee Habituation');
        $m5_2->setDescription("Support Rubondo National Park's chimpanzee habituation project to improve chimps' visibility and contact for tourism experience.");
        $m5_2->setStatus(MilestoneStatus::OPEN);
        $m5_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m5_2);
        $this->createTarget($manager, $m5_2, 'Sightings above 100%, visibility 8-12m and 3hrs:45m in Northern Chimps subgroup', 0);
        $this->createTarget($manager, $m5_2, 'Sightings above 50%, visibility 10-15m and 1hr in Southern Chimps subgroup', 1);
        $this->createTarget($manager, $m5_2, '20km+ new trails in Southern Rubondo identified and cleared', 2);
        $this->createTarget($manager, $m5_2, '5 Chimpanzee individuals identified and documented', 3);
        $this->createTarget($manager, $m5_2, '17 chimp trackers trained on guiding techniques, 1st Aid, Navigation, and Botany', 4);
        $this->createTarget($manager, $m5_2, '7 Community trackers attended English courses', 5);
        $this->createTarget($manager, $m5_2, '7 community trackers equipped with Licence D', 6);
        $this->createTarget($manager, $m5_2, '4-year action plan report developed and Reviewed MoU between HGF and TANAPA', 7);
        $this->createTarget($manager, $m5_2, 'New marketing materials for Rubondo chimp products', 8);

        $this->createTask($manager, $m5_2, '5.2.1 Northern chimps habituation', 'Continued habituation of the northern chimps sub-group.', TaskStatus::TODO, TaskPriority::HIGH, $meleck);
        $this->createTask($manager, $m5_2, '5.2.2 Southern chimps mapping & monitoring', 'Start habituating the southern chimp subgroup through mapping and monitoring.', TaskStatus::TODO, TaskPriority::HIGH, $meleck);
        $this->createTask($manager, $m5_2, '5.2.3 Chimp tourism & tracker training', 'Strengthen chimpanzee tourism through habituation and tracker training.', TaskStatus::TODO, TaskPriority::MEDIUM, $meleck);
        $this->createTask($manager, $m5_2, '5.2.4 Marketing with TANAPA', 'Improve marketing and advertising of the Chimp product with TANAPA.', TaskStatus::TODO, TaskPriority::MEDIUM, $meleck);
        $this->createTask($manager, $m5_2, '5.2.5 New 4-year action plan', 'Develop a new 4-year action plan that includes a diversified fundraising strategy.', TaskStatus::TODO, TaskPriority::HIGH, $meleck);

        // ============================================
        // PROJECT F: Narrative Change & Strategic Influence
        // ============================================
        $projectF = new Project();
        $projectF->setName('F. Narrative Change & Strategic Influence');
        $projectF->setDescription('Goal 2: Narrative change and strategic influence through public awareness, stakeholder perception, policy, regional networks, and capacity building.');
        $projectF->setStatus(ProjectStatus::ACTIVE);
        $projectF->setOwner($adminUser);
        $projectF->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectF->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectF);

        $this->addMember($manager, $projectF, $adminUser, $projectManager);

        // --- Milestone 6.1: Public Awareness ---
        $m6_1 = new Milestone();
        $m6_1->setProject($projectF);
        $m6_1->setName('6.1 Public Awareness');
        $m6_1->setDescription("Develop a smart communication strategies, providing a structured approach to evaluate progress, measure outcomes, and determine whether desired goals have been achieved.");
        $m6_1->setStatus(MilestoneStatus::OPEN);
        $m6_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m6_1);
        $this->createTarget($manager, $m6_1, '10 TV shows on WMA related issues', 0);
        $this->createTarget($manager, $m6_1, '3 radio stations broadcasting at local level on WMA issues', 1);
        $this->createTarget($manager, $m6_1, '10 WMAs independently posting on social media', 2);

        $this->createTask($manager, $m6_1, '6.1.1 National & local media awareness', 'National and local media and general public awareness.', TaskStatus::TODO, TaskPriority::HIGH, null);

        // --- Milestone 6.2: Stakeholder Perception ---
        $m6_2 = new Milestone();
        $m6_2->setProject($projectF);
        $m6_2->setName('6.2 Stakeholder Perception');
        $m6_2->setDescription("Develop a stakeholder narrative benchmark tool and test.");
        $m6_2->setStatus(MilestoneStatus::OPEN);
        $m6_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m6_2);
        $this->createTarget($manager, $m6_2, 'Benchmarking tool tested', 0);

        $this->createTask($manager, $m6_2, '6.2.1 Narrative benchmark assessment', 'Stakeholder narrative benchmark assessment.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 6.3: Policy ---
        $m6_3 = new Milestone();
        $m6_3->setProject($projectF);
        $m6_3->setName('6.3 Policy');
        $m6_3->setDescription("Initiate and facilitate a forum for Advocacy and policy reform.");
        $m6_3->setStatus(MilestoneStatus::OPEN);
        $m6_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m6_3);
        $this->createTarget($manager, $m6_3, '1x Plan and budget developed with clear roles of network team, clear goals, monitoring and outcomes developed and shared', 0);
        $this->createTarget($manager, $m6_3, '4x Quarterly Reports developed', 1);

        $this->createTask($manager, $m6_3, '6.3.1 Policy network & facilitation', 'Policy network and facilitation.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 6.4: Regional Networks ---
        $m6_4 = new Milestone();
        $m6_4->setProject($projectF);
        $m6_4->setName('6.4 Regional Networks');
        $m6_4->setDescription("Engage with regional CLC networks to continue to share Honeyguide tools and approaches.");
        $m6_4->setStatus(MilestoneStatus::OPEN);
        $m6_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m6_4);
        $this->createTarget($manager, $m6_4, 'Attended BCC conference', 0);
        $this->createTarget($manager, $m6_4, 'Engaged in quarterly CLC network calls', 1);

        $this->createTask($manager, $m6_4, '6.4.1 Regional CLC narrative', 'Regional narrative on CLC.', TaskStatus::TODO, TaskPriority::LOW, null);

        // --- Milestone 6.5: Capacity Building ---
        $m6_5 = new Milestone();
        $m6_5->setProject($projectF);
        $m6_5->setName('6.5 Capacity Building');
        $m6_5->setDescription("Building capacity in HGF for policy and narrative change.");
        $m6_5->setStatus(MilestoneStatus::OPEN);
        $m6_5->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m6_5);
        $this->createTarget($manager, $m6_5, '2 key persons trained in advocacy and media', 0);

        $this->createTask($manager, $m6_5, '6.5.1 Advocacy & media training', 'Training and equipment for advocacy and media teams.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // ============================================
        // PROJECT G: Finance and Admin
        // ============================================
        $projectG = new Project();
        $projectG->setName('G. Finance and Admin');
        $projectG->setDescription('Financial management, HR, IT infrastructure, asset/risk management, and workshop operations.');
        $projectG->setStatus(ProjectStatus::ACTIVE);
        $projectG->setOwner($adminUser);
        $projectG->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectG->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectG);

        $this->addMember($manager, $projectG, $adminUser, $projectManager);

        // --- Milestone 7.1: Financial Management ---
        $m7_1 = new Milestone();
        $m7_1->setProject($projectG);
        $m7_1->setName('7.1 Financial Management');
        $m7_1->setDescription("Strengthen Financial Management Systems and Procedures to ensure efficiency, transparency, and accountability.");
        $m7_1->setStatus(MilestoneStatus::OPEN);
        $m7_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m7_1);
        $this->createTarget($manager, $m7_1, 'Training on the Financial and Procurement Manual in use', 0);
        $this->createTarget($manager, $m7_1, 'Staff trained on financial systems and reporting', 1);
        $this->createTarget($manager, $m7_1, 'An automated/digitized finance system reduces errors and delays', 2);
        $this->createTarget($manager, $m7_1, 'Procurement Manual developed and approved by the board', 3);
        $this->createTarget($manager, $m7_1, 'Transparent, competitive, and compliant procurement system operational', 4);
        $this->createTarget($manager, $m7_1, 'Stronger donor confidence due to improved accountability and compliance', 5);

        $this->createTask($manager, $m7_1, '7.1.1 Finance & procurement manual awareness', 'Awareness of finance and procurement manual procedures and practices.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_1, '7.1.2 Internal audit & compliance', 'Strengthen internal audit and compliance mechanisms and follow up on Audit recommendations.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_1, '7.1.3 Donor-specific dashboards & automation', 'Enhance financial reporting by introducing donor-specific dashboards and automating report generation.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_1, '7.1.4 Long-term financial planning', 'Strategic long-term financial planning.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_1, '7.1.5 Budget & cashflow monitoring', 'Annual Budget and Cashflow development and monitoring.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_1, '7.1.6 e-Asset & e-Procurement rollout', 'Roll out e-Asset management (Asset lists, regular inventory, valuation, security, insurance) and improve e-procurement system within the finance system.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 7.2: HR Management ---
        $m7_2 = new Milestone();
        $m7_2->setProject($projectG);
        $m7_2->setName('7.2 HR Management');
        $m7_2->setDescription("To strengthen HR foundations by improving systems, structures, and culture moving from the current baseline to a significantly higher level of efficiency by the end of 2026.");
        $m7_2->setStatus(MilestoneStatus::OPEN);
        $m7_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m7_2);
        $this->createTarget($manager, $m7_2, 'Job profiles and grades finalized; Competency matrix approved; HR framework published', 0);
        $this->createTarget($manager, $m7_2, '100% of staff appraised bi-annually; 2 training sessions and mentorship program implemented', 1);
        $this->createTarget($manager, $m7_2, 'Succession plan for executives completed; 3 departmental pipelines developed', 2);
        $this->createTarget($manager, $m7_2, '2 leadership workshops delivered; 100% managers trained in decision-making and coaching', 3);
        $this->createTarget($manager, $m7_2, '1 culture survey conducted; Recognition program launched; Engagement index improved by 15%', 4);
        $this->createTarget($manager, $m7_2, 'Data protection policy and registers developed; All staff trained on compliance', 5);

        $this->createTask($manager, $m7_2, '7.2.1 Workforce planning & job evaluation', 'Workforce Planning, Compensation and Benefits – Develop job profiles, competency models, and conduct a comprehensive job evaluation to establish clear job grades.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_2, '7.2.2 Performance management improvement', 'Strengthen the performance management system and support employee development through training, mentorship, and cross-department exposure.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_2, '7.2.3 Staff training & development', 'Identify organization development priority and ensure implementation of staff development activities and measure its impact.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_2, '7.2.4 HRIS integration & consolidation', 'Automate all HR processes and consolidate different HR systems to one system.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_2, '7.2.5 Culture & engagement improvement', 'Launch engagement programs with surveys, accountability initiatives, recognition schemes, and a strong Employer Value Proposition.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_2, '7.2.6 HR compliance & data protection', 'Implement a personal data protection compliance program with policies, training, registers, and clear oversight roles.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 7.3: IT ---
        $m7_3 = new Milestone();
        $m7_3->setProject($projectG);
        $m7_3->setName('7.3 IT');
        $m7_3->setDescription("Strengthen IT infrastructure and digital tools by enhancing automation, optimizing HR and asset management processes, ensuring compliance with data protection standards, and improving system reliability.");
        $m7_3->setStatus(MilestoneStatus::OPEN);
        $m7_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m7_3);
        $this->createTarget($manager, $m7_3, 'All five core modules (Leave, Payroll, Performance, Assets, M&E) developed, tested, and deployed', 0);
        $this->createTarget($manager, $m7_3, 'Data Protection Policy and compliance framework fully developed, approved, and rolled out', 1);
        $this->createTarget($manager, $m7_3, 'ICT infrastructure maintained at 95%+ uptime, with quarterly preventive maintenance and license renewals', 2);
        $this->createTarget($manager, $m7_3, 'Shared digital workspace for WMA resources established and actively used', 3);

        $this->createTask($manager, $m7_3, '7.3.1 App development (Leave, Payroll, etc.)', 'App Development – Leave, Payroll, Performance, Assets, M&E, HGF Website, Honeyguide Learning.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_3, '7.3.2 Data protection & compliance', 'Establish strong data protection measures aligned with national and international standards.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_3, '7.3.3 Tech support & maintenance', 'Deliver regular IT support for internet, hardware, software, and maintain in-house web/mobile applications. Provide IT equipment and upgrade mobile internet infrastructure.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_3, '7.3.4 Collaboration & knowledge sharing', 'Create a shared digital workspace for WMA resources and support the Honeyguide Learning Initiative with platforms, tools, and knowledge-sharing systems.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 7.4: Asset and Risk Management ---
        $m7_4 = new Milestone();
        $m7_4->setProject($projectG);
        $m7_4->setName('7.4 Asset and Risk Management');
        $m7_4->setDescription("Enhance and digitalize asset and risk management systems to ensure real-time accountability, proactive risk monitoring, and long-term sustainability of organizational resources.");
        $m7_4->setStatus(MilestoneStatus::OPEN);
        $m7_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m7_4);
        $this->createTarget($manager, $m7_4, 'Digital Asset Management System (linked to finance system) operational, with quarterly automated reports and annual physical verification completed', 0);
        $this->createTarget($manager, $m7_4, 'Comprehensive Risk Management Framework finalized and implemented, with quarterly risk review reports and updated risk register', 1);

        $this->createTask($manager, $m7_4, '7.4.1 Asset management system', 'Maintain and optimize asset management system for efficiency, accountability, and sustainability.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_4, '7.4.2 Risk management framework', 'Strengthen organizational risk management framework and implement monitoring processes for financial, cyber, and political risks.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 7.5: Workshop ---
        $m7_5 = new Milestone();
        $m7_5->setProject($projectG);
        $m7_5->setName('7.5 Workshop');
        $m7_5->setDescription("Well equipped and professionally-run workshop Operations for Better Vehicle and Equipment Management.");
        $m7_5->setStatus(MilestoneStatus::OPEN);
        $m7_5->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m7_5);
        $this->createTarget($manager, $m7_5, '100% of fleet serviced on schedule, with >95% operational readiness', 0);
        $this->createTarget($manager, $m7_5, '90%+ of repairs completed within 24 hours', 1);
        $this->createTarget($manager, $m7_5, 'Standardized checklist adopted, reducing unscheduled repairs by 15% in Q1', 2);
        $this->createTarget($manager, $m7_5, '100% of vehicles pass safety inspections; zero workshop-related accidents', 3);
        $this->createTarget($manager, $m7_5, '100% of workshop staff trained and adhering to SOPs by year-end', 4);
        $this->createTarget($manager, $m7_5, 'Accurate reports submitted on time with actionable insights', 5);

        $this->createTask($manager, $m7_5, '7.5.1 Fleet management & safety', 'Enhancing scheduled Workshop and vehicles by implementing a Fleet Management System, standardize Workshop Processes and Enhance Safety & Compliance Culture.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_5, '7.5.2 Zero lost-time injuries target', 'Achieve Zero Lost-Time Injuries in the workshop and for fleet operations.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m7_5, '7.5.3 Spare parts & lifecycle analysis', 'Analyze and consolidate spare part suppliers for bulk discounts and conduct a lifecycle cost analysis for each vehicle.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_5, '7.5.4 Fuel & maintenance metrics', 'Monitor and report on key metrics: Fuel Use, Maintenance Cost per Kilometer.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m7_5, '7.5.5 Quarterly workshop review', 'Perform quarterly internal review on workshop practices.', TaskStatus::TODO, TaskPriority::LOW, null);

        // ============================================
        // PROJECT H: Communication and Fundraising
        // ============================================
        $projectH = new Project();
        $projectH->setName('H. Communication and Fundraising');
        $projectH->setDescription('Fundraising, systems/tools development, international and national communications.');
        $projectH->setStatus(ProjectStatus::ACTIVE);
        $projectH->setOwner($adminUser);
        $projectH->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectH->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectH);

        $this->addMember($manager, $projectH, $adminUser, $projectManager);

        // --- Milestone 8.1: Fundraising ---
        $m8_1 = new Milestone();
        $m8_1->setProject($projectH);
        $m8_1->setName('8.1 Fundraising');
        $m8_1->setDescription("Design + develop systems to enhance collaborative fundraising efforts.");
        $m8_1->setStatus(MilestoneStatus::OPEN);
        $m8_1->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m8_1);
        $this->createTarget($manager, $m8_1, 'Key long-term donors maintained or increased contribution, at least one donor increased support by 20%', 0);
        $this->createTarget($manager, $m8_1, 'Funding gap for 2026 reduced by 100%', 1);
        $this->createTarget($manager, $m8_1, 'Funding gap for 2027 reduced by 70%', 2);
        $this->createTarget($manager, $m8_1, 'Engaged in productive discussions with at least 2 donors that can contribute >50k per year', 3);
        $this->createTarget($manager, $m8_1, 'Responded to at least 1 large multi-year international call (>400k - Darwin)', 4);
        $this->createTarget($manager, $m8_1, 'MOUs and agreements with partners that include joint fundraising', 5);
        $this->createTarget($manager, $m8_1, 'Raised necessary funds to support Special Programs (K9 + Rubondo) - HWC Lab potential', 6);

        $this->createTask($manager, $m8_1, '8.1.1 Top ten donor engagement', 'Strategically engage with our current top ten donors to encourage them to increase their contribution.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m8_1, '8.1.2 Broaden donor base', 'Broaden current donor base by actively pursuing potential donors that have an interest in Honeyguide priority areas.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m8_1, '8.1.3 Funding opportunities & proposals', 'Monitor and respond to active funding opportunities and calls for proposals for financial assistance.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m8_1, '8.1.4 Joint funding tools & agreements', 'Develop tools and agreements with key partners to streamline joint funding applications.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_1, '8.1.5 Special programs funding partners', 'Strategically search for funding partners that have an interest in any of the special programs.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 8.2: Systems and Tool Development ---
        $m8_2 = new Milestone();
        $m8_2->setProject($projectH);
        $m8_2->setName('8.2 Systems and Tool Development');
        $m8_2->setDescription("Design and develop systems and tools (AI) for the organization to support its communications and fundraising efforts.");
        $m8_2->setStatus(MilestoneStatus::OPEN);
        $m8_2->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m8_2);
        $this->createTarget($manager, $m8_2, 'Collaborative dashboard with updated information/data tracking organizational impact 2017-2025', 0);
        $this->createTarget($manager, $m8_2, 'Shared dashboard monitoring HGF impact on national WMA strategy 2023-2033', 1);
        $this->createTarget($manager, $m8_2, 'Active online library with easy search and retrieve functions, HGF team trained', 2);
        $this->createTarget($manager, $m8_2, 'Monthly updating from WhatsApp groups and organizing photos on Smugmug', 3);

        $this->createTask($manager, $m8_2, '8.2.1 Build comms tools capacity', 'Build capacity with new tools for comms.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_2, '8.2.2 Comms team data training', 'Training comms team and coaching on use and access of the data.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_2, '8.2.3 AI for communications', 'Design, test, and develop knowledge resource of AI for communications.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_2, '8.2.4 Communications App management', 'Manage and maintain the Honeyguide Communications App, training and coach Honeyguide team to participate and update activities in the app.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // --- Milestone 8.3: Comms International ---
        $m8_3 = new Milestone();
        $m8_3->setProject($projectH);
        $m8_3->setName('8.3 Comms International');
        $m8_3->setDescription("Design and produce creative, informative materials highlighting our unique approach and impact.");
        $m8_3->setStatus(MilestoneStatus::OPEN);
        $m8_3->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m8_3);
        $this->createTarget($manager, $m8_3, 'Four communication campaigns developed annually, one per quarter', 0);
        $this->createTarget($manager, $m8_3, 'Donor Visibility Guidelines: one-page document per donor', 1);
        $this->createTarget($manager, $m8_3, 'Annual Report produced', 2);
        $this->createTarget($manager, $m8_3, 'Case Studies highlighting key field activities, produced quarterly', 3);
        $this->createTarget($manager, $m8_3, 'Brochures & Presentations updated biannually', 4);
        $this->createTarget($manager, $m8_3, 'Four 5-minute promotional videos produced annually', 5);
        $this->createTarget($manager, $m8_3, 'Website Redesign: Honeyguide Innovation section added', 6);
        $this->createTarget($manager, $m8_3, 'Communications Plan for 2026 created', 7);

        $this->createTask($manager, $m8_3, '8.3.1 Thematic communication campaigns', 'Package and produce communication campaigns in the form of thematic areas, where each theme is supported by a data sheet and editorial (for blogs, newsletters, social media and webinars).', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m8_3, '8.3.2 One-way communications (blogs, etc.)', 'Produce regular one-way communications (blogs, publications, newsletters, videos) and monitor views.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_3, '8.3.3 Two-way communications (webinars, etc.)', 'Produce material to support two-way communications (webinar, 1-1 meetings, presentations).', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_3, '8.3.4 Website updates', 'Ongoing updates in the website with current information (introduction Honeyguide Innovation) and organizational development.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_3, '8.3.5 2026 Communications plan', 'Create a 2026 Communications plan.', TaskStatus::TODO, TaskPriority::HIGH, null);

        // --- Milestone 8.4: Comms National ---
        $m8_4 = new Milestone();
        $m8_4->setProject($projectH);
        $m8_4->setName('8.4 Comms National');
        $m8_4->setDescription("Design, test, and develop knowledge resources to be shared both internally + externally.");
        $m8_4->setStatus(MilestoneStatus::OPEN);
        $m8_4->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m8_4);
        $this->createTarget($manager, $m8_4, 'Produced quarterly newsletter in Swahili', 0);
        $this->createTarget($manager, $m8_4, 'Weekly posts in social media and shared reports', 1);
        $this->createTarget($manager, $m8_4, 'Posters designed and shared of Honeyguide work', 2);
        $this->createTarget($manager, $m8_4, 'Honeyguide is live in Swahili', 3);

        $this->createTask($manager, $m8_4, '8.4.1 Swahili quarterly newsletter', 'Production of Newsletter (every quarter) in Swahili with project updates and organization news.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_4, '8.4.2 Swahili social media posts', 'Regular social media posts in Swahili.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m8_4, '8.4.3 Honeyguide awareness posters', 'Design and develop Honeyguide awareness posters (posters to show Honeyguide work and approach) and publications in Swahili.', TaskStatus::TODO, TaskPriority::LOW, null);
        $this->createTask($manager, $m8_4, '8.4.4 Swahili website', 'Design and develop Honeyguide Swahili website and publish.', TaskStatus::TODO, TaskPriority::MEDIUM, null);

        // ============================================
        // PROJECT I: Honeyguide Board Governance
        // ============================================
        $projectI = new Project();
        $projectI->setName('I. Honeyguide Board Governance');
        $projectI->setDescription('An effective board that are able to perform their roles to support and guide the organization.');
        $projectI->setStatus(ProjectStatus::ACTIVE);
        $projectI->setOwner($adminUser);
        $projectI->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $projectI->setEndDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($projectI);

        $this->addMember($manager, $projectI, $adminUser, $projectManager);

        // --- Milestone 9.0: Honeyguide Oversight ---
        $m9_0 = new Milestone();
        $m9_0->setProject($projectI);
        $m9_0->setName('9.0 Honeyguide Oversight');
        $m9_0->setDescription("An effective board that are able to perform their roles to support and guide the organization.");
        $m9_0->setStatus(MilestoneStatus::OPEN);
        $m9_0->setDueDate(new \DateTimeImmutable('2026-12-31'));
        $manager->persist($m9_0);
        $this->createTarget($manager, $m9_0, 'At least 2 new board members recruited by end of year', 0);
        $this->createTarget($manager, $m9_0, 'An online training course is designed and shared to the board members; all board members have completed the course', 1);
        $this->createTarget($manager, $m9_0, 'Revised constitution in place. Onboarding procedure in place for new members', 2);
        $this->createTarget($manager, $m9_0, 'Annual meeting dates communicated in January. 4 online board meetings held. 1 AGM held. Annual retreat of at least 2 days held', 3);

        $this->createTask($manager, $m9_0, '9.1.1 Recruit diverse board members', 'Recruit additional board members that come from diverse backgrounds and support our board development plan.', TaskStatus::TODO, TaskPriority::HIGH, null);
        $this->createTask($manager, $m9_0, '9.1.2 Board training & onboarding', 'Provide the board with training materials and a training and onboarding process to build the capacity of the board members to understand their roles.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m9_0, '9.1.3 Board policies & procedures', 'Develop board guiding policies, procedures and systems that continue to aid the board\'s capability to perform their roles.', TaskStatus::TODO, TaskPriority::MEDIUM, null);
        $this->createTask($manager, $m9_0, '9.1.4 Board meetings & AGM management', 'Plan and manage all documentation and procedures for board meetings including the committees meetings, AGM and annual retreat.', TaskStatus::TODO, TaskPriority::HIGH, null);

        // ============================================
        // TAGS
        // ============================================
        $this->createTag($manager, 'governance', '#3b82f6', $adminUser);
        $this->createTag($manager, 'management', '#22c55e', $adminUser);
        $this->createTag($manager, 'protection', '#ef4444', $adminUser);
        $this->createTag($manager, 'HWC', '#f97316', $adminUser);
        $this->createTag($manager, 'livelihoods', '#8b5cf6', $adminUser);
        $this->createTag($manager, 'M&E', '#06b6d4', $adminUser);
        $this->createTag($manager, 'GIS', '#14b8a6', $adminUser);
        $this->createTag($manager, 'fundraising', '#eab308', $adminUser);
        $this->createTag($manager, 'communications', '#ec4899', $adminUser);
        $this->createTag($manager, 'finance', '#84cc16', $adminUser);
        $this->createTag($manager, 'HR', '#d946ef', $adminUser);
        $this->createTag($manager, 'IT', '#6b7280', $adminUser);

        // ============================================
        // GANTT TEST DATA: Nested subtasks for testing
        // ============================================
        $ganttTestProject = new Project();
        $ganttTestProject->setName('Gantt Test Project');
        $ganttTestProject->setDescription('Project with nested tasks for testing Gantt chart display');
        $ganttTestProject->setStatus(ProjectStatus::ACTIVE);
        $ganttTestProject->setOwner($adminUser);
        $ganttTestProject->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $ganttTestProject->setEndDate(new \DateTimeImmutable('2026-06-30'));
        $manager->persist($ganttTestProject);

        $this->addMember($manager, $ganttTestProject, $adminUser, $projectManager);
        $this->addMember($manager, $ganttTestProject, $max, $projectMember);

        $ganttMilestone = new Milestone();
        $ganttMilestone->setProject($ganttTestProject);
        $ganttMilestone->setName('Website Redesign');
        $ganttMilestone->setDescription('Complete website redesign with nested task structure');
        $ganttMilestone->setStatus(MilestoneStatus::OPEN);
        $ganttMilestone->setDueDate(new \DateTimeImmutable('2026-06-30'));
        $manager->persist($ganttMilestone);

        // Level 0: Parent tasks (1, 2, 3, 4)
        $planning = $this->createTask($manager, $ganttMilestone, '1 Planning Phase', 'Initial planning and requirements gathering', TaskStatus::COMPLETED, TaskPriority::HIGH, $adminUser, null, '2026-01-06', '2026-01-31');

        // Level 1: Subtasks of Planning (1.1, 1.2, 1.3)
        $requirements = $this->createTask($manager, $ganttMilestone, '1.1 Requirements Analysis', 'Gather and document requirements', TaskStatus::COMPLETED, TaskPriority::HIGH, $max, $planning, '2026-01-06', '2026-01-17');
        $wireframes = $this->createTask($manager, $ganttMilestone, '1.2 Create Wireframes', 'Design wireframes for all pages', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $planning, '2026-01-13', '2026-01-24');
        $techSpec = $this->createTask($manager, $ganttMilestone, '1.3 Technical Specification', 'Write technical specs', TaskStatus::COMPLETED, TaskPriority::HIGH, $adminUser, $planning, '2026-01-20', '2026-01-31');

        // Level 2: Sub-subtasks of Requirements Analysis (1.1.1, 1.1.2, 1.1.3)
        $this->createTask($manager, $ganttMilestone, '1.1.1 Stakeholder Interviews', 'Interview key stakeholders', TaskStatus::COMPLETED, TaskPriority::HIGH, $max, $requirements, '2026-01-06', '2026-01-10');
        $this->createTask($manager, $ganttMilestone, '1.1.2 Document Current System', 'Document existing system', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $requirements, '2026-01-08', '2026-01-14');
        $this->createTask($manager, $ganttMilestone, '1.1.3 Define User Stories', 'Create user stories', TaskStatus::COMPLETED, TaskPriority::HIGH, $adminUser, $requirements, '2026-01-13', '2026-01-17');

        // Level 2: Sub-subtasks of Wireframes (1.2.1, 1.2.2, 1.2.3)
        $this->createTask($manager, $ganttMilestone, '1.2.1 Homepage Wireframe', 'Design homepage layout', TaskStatus::COMPLETED, TaskPriority::HIGH, $max, $wireframes, '2026-01-13', '2026-01-17');
        $this->createTask($manager, $ganttMilestone, '1.2.2 Dashboard Wireframe', 'Design dashboard layout', TaskStatus::COMPLETED, TaskPriority::HIGH, $max, $wireframes, '2026-01-15', '2026-01-20');
        $this->createTask($manager, $ganttMilestone, '1.2.3 Mobile Wireframes', 'Design mobile responsive layouts', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $wireframes, '2026-01-20', '2026-01-24');

        // Level 0: Another parent task (2)
        $design = $this->createTask($manager, $ganttMilestone, '2 Design Phase', 'Visual design and prototyping', TaskStatus::IN_PROGRESS, TaskPriority::HIGH, $max, null, '2026-02-01', '2026-02-28');

        // Level 1: Subtasks of Design (2.1, 2.2, 2.3)
        $visualDesign = $this->createTask($manager, $ganttMilestone, '2.1 Visual Design', 'Create visual designs', TaskStatus::IN_PROGRESS, TaskPriority::HIGH, $max, $design, '2026-02-01', '2026-02-14');
        $prototype = $this->createTask($manager, $ganttMilestone, '2.2 Interactive Prototype', 'Build clickable prototype', TaskStatus::TODO, TaskPriority::MEDIUM, $max, $design, '2026-02-10', '2026-02-21');
        $this->createTask($manager, $ganttMilestone, '2.3 Design Review', 'Review and approve designs', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $design, '2026-02-22', '2026-02-28');

        // Level 2: Sub-subtasks of Visual Design (2.1.1, 2.1.2, 2.1.3, 2.1.4)
        $this->createTask($manager, $ganttMilestone, '2.1.1 Color Palette', 'Define color scheme', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $visualDesign, '2026-02-01', '2026-02-03');
        $this->createTask($manager, $ganttMilestone, '2.1.2 Typography', 'Select fonts and type scale', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $visualDesign, '2026-02-03', '2026-02-05');
        $iconDesign = $this->createTask($manager, $ganttMilestone, '2.1.3 Icon Design', 'Design custom icons', TaskStatus::IN_PROGRESS, TaskPriority::LOW, $max, $visualDesign, '2026-02-05', '2026-02-10');
        $this->createTask($manager, $ganttMilestone, '2.1.4 Component Library', 'Build UI component library', TaskStatus::IN_PROGRESS, TaskPriority::HIGH, $max, $visualDesign, '2026-02-08', '2026-02-14');

        // Level 3: Sub-sub-subtasks of Icon Design (2.1.3.1, 2.1.3.2, 2.1.3.3)
        $this->createTask($manager, $ganttMilestone, '2.1.3.1 Navigation Icons', 'Design nav icons', TaskStatus::COMPLETED, TaskPriority::MEDIUM, $max, $iconDesign, '2026-02-05', '2026-02-07');
        $this->createTask($manager, $ganttMilestone, '2.1.3.2 Action Icons', 'Design action icons', TaskStatus::IN_PROGRESS, TaskPriority::MEDIUM, $max, $iconDesign, '2026-02-07', '2026-02-09');
        $this->createTask($manager, $ganttMilestone, '2.1.3.3 Status Icons', 'Design status indicators', TaskStatus::TODO, TaskPriority::LOW, $max, $iconDesign, '2026-02-09', '2026-02-10');

        // Level 0: Development phase (3)
        $development = $this->createTask($manager, $ganttMilestone, '3 Development Phase', 'Frontend and backend development', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, null, '2026-03-01', '2026-05-31');

        // Level 1: Subtasks of Development (3.1, 3.2, 3.3)
        $frontend = $this->createTask($manager, $ganttMilestone, '3.1 Frontend Development', 'Build frontend components', TaskStatus::TODO, TaskPriority::HIGH, $max, $development, '2026-03-01', '2026-04-15');
        $backend = $this->createTask($manager, $ganttMilestone, '3.2 Backend Development', 'Build API and services', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $development, '2026-03-15', '2026-05-15');
        $this->createTask($manager, $ganttMilestone, '3.3 Integration Testing', 'Test frontend/backend integration', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $development, '2026-05-01', '2026-05-31');

        // Level 2: Sub-subtasks of Frontend (3.1.1, 3.1.2, 3.1.3, 3.1.4)
        $this->createTask($manager, $ganttMilestone, '3.1.1 Setup Build System', 'Configure webpack/vite', TaskStatus::TODO, TaskPriority::HIGH, $max, $frontend, '2026-03-01', '2026-03-05');
        $this->createTask($manager, $ganttMilestone, '3.1.2 Implement Components', 'Build reusable components', TaskStatus::TODO, TaskPriority::HIGH, $max, $frontend, '2026-03-05', '2026-03-25');
        $this->createTask($manager, $ganttMilestone, '3.1.3 Page Templates', 'Build page templates', TaskStatus::TODO, TaskPriority::MEDIUM, $max, $frontend, '2026-03-20', '2026-04-10');
        $this->createTask($manager, $ganttMilestone, '3.1.4 Responsive Testing', 'Test on all devices', TaskStatus::TODO, TaskPriority::MEDIUM, $max, $frontend, '2026-04-08', '2026-04-15');

        // Level 2: Sub-subtasks of Backend (3.2.1, 3.2.2, 3.2.3, 3.2.4)
        $this->createTask($manager, $ganttMilestone, '3.2.1 Database Schema', 'Design and implement DB', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $backend, '2026-03-15', '2026-03-25');
        $this->createTask($manager, $ganttMilestone, '3.2.2 API Endpoints', 'Build REST API', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $backend, '2026-03-22', '2026-04-20');
        $this->createTask($manager, $ganttMilestone, '3.2.3 Authentication', 'Implement auth system', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, $backend, '2026-04-15', '2026-05-01');
        $this->createTask($manager, $ganttMilestone, '3.2.4 Performance Optimization', 'Optimize queries and caching', TaskStatus::TODO, TaskPriority::MEDIUM, $adminUser, $backend, '2026-05-01', '2026-05-15');

        // Level 0: Launch phase (4)
        $this->createTask($manager, $ganttMilestone, '4 Launch Phase', 'Deployment and go-live', TaskStatus::TODO, TaskPriority::HIGH, $adminUser, null, '2026-06-01', '2026-06-30');

        $manager->flush();
    }

    private function addMember(
        ObjectManager $manager,
        Project $project,
        User $user,
        Role $role
    ): ProjectMember {
        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($role);
        $manager->persist($member);

        return $member;
    }

    private function createTask(
        ObjectManager $manager,
        Milestone $milestone,
        string $title,
        string $description,
        TaskStatus $status,
        TaskPriority $priority,
        ?User $assignee,
        ?Task $parent = null,
        ?string $startDate = null,
        ?string $dueDate = '2026-12-31'
    ): Task {
        // Auto-increment position per project
        $projectId = $milestone->getProject()->getId()->toString();
        if (!isset($this->projectTaskPositions[$projectId])) {
            $this->projectTaskPositions[$projectId] = 0;
        }
        $position = $this->projectTaskPositions[$projectId]++;

        $task = new Task();
        $task->setMilestone($milestone);
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setPosition($position);

        if ($parent) {
            $task->setParent($parent);
        }

        if ($startDate) {
            $task->setStartDate(new \DateTimeImmutable($startDate));
        }

        if ($dueDate) {
            $task->setDueDate(new \DateTimeImmutable($dueDate));
        }

        $manager->persist($task);

        if ($assignee) {
            $taskAssignee = new TaskAssignee();
            $taskAssignee->setTask($task);
            $taskAssignee->setUser($assignee);
            $taskAssignee->setAssignedBy($milestone->getProject()->getOwner());
            $manager->persist($taskAssignee);
        }

        return $task;
    }

    private function createTarget(
        ObjectManager $manager,
        Milestone $milestone,
        string $description,
        int $position
    ): MilestoneTarget {
        $target = new MilestoneTarget();
        $target->setMilestone($milestone);
        $target->setDescription($description);
        $target->setPosition($position);
        $manager->persist($target);

        return $target;
    }

    private function createTag(
        ObjectManager $manager,
        string $name,
        string $color,
        User $createdBy
    ): Tag {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setColor($color);
        $tag->setCreatedBy($createdBy);
        $manager->persist($tag);

        return $tag;
    }

    private function createPersonalProject(
        ObjectManager $manager,
        User $user,
        Role $managerRole
    ): Project {
        $project = new Project();
        $project->setName($user->getFirstName() . "'s Personal Project");
        $project->setOwner($user);
        $project->setIsPublic(false);
        $project->setIsPersonal(true);
        $project->setDescription('Your personal workspace for tasks and projects.');
        $manager->persist($project);

        // Add owner as project manager
        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($managerRole);
        $manager->persist($member);

        // Create a default milestone
        $milestone = new Milestone();
        $milestone->setName('General');
        $milestone->setProject($project);
        $manager->persist($milestone);

        return $project;
    }
}

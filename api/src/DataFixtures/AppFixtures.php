<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Milestone;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\Comment;
use App\Enum\ProjectStatus;
use App\Enum\ProjectRole;
use App\Enum\MilestoneStatus;
use App\Enum\TaskStatus;
use App\Enum\TaskPriority;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
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
        $manager->persist($adminUser);

        $johnDoe = new User();
        $johnDoe->setEmail('john@example.com');
        $johnDoe->setFirstName('John');
        $johnDoe->setLastName('Doe');
        $johnDoe->setPassword($this->passwordHasher->hashPassword($johnDoe, 'password123'));
        $johnDoe->setIsVerified(true);
        $manager->persist($johnDoe);

        $janeSmith = new User();
        $janeSmith->setEmail('jane@example.com');
        $janeSmith->setFirstName('Jane');
        $janeSmith->setLastName('Smith');
        $janeSmith->setPassword($this->passwordHasher->hashPassword($janeSmith, 'password123'));
        $janeSmith->setIsVerified(true);
        $manager->persist($janeSmith);

        // ============================================
        // PROJECT 1: E-Commerce Platform
        // ============================================
        $project1 = new Project();
        $project1->setName('E-Commerce Platform');
        $project1->setDescription('Build a modern e-commerce platform with React frontend and Symfony backend. Features include product catalog, shopping cart, checkout, and admin panel.');
        $project1->setStatus(ProjectStatus::ACTIVE);
        $project1->setOwner($testUser);
        $project1->setStartDate(new \DateTimeImmutable('-30 days'));
        $project1->setEndDate(new \DateTimeImmutable('+60 days'));
        $manager->persist($project1);

        // Project 1 Members
        $member1 = new ProjectMember();
        $member1->setProject($project1);
        $member1->setUser($testUser);
        $member1->setRole(ProjectRole::ADMIN);
        $manager->persist($member1);

        $member2 = new ProjectMember();
        $member2->setProject($project1);
        $member2->setUser($johnDoe);
        $member2->setRole(ProjectRole::MEMBER);
        $manager->persist($member2);

        $member3 = new ProjectMember();
        $member3->setProject($project1);
        $member3->setUser($janeSmith);
        $member3->setRole(ProjectRole::MEMBER);
        $manager->persist($member3);

        // Project 1 - Milestone 1: Backend Setup
        $milestone1_1 = new Milestone();
        $milestone1_1->setProject($project1);
        $milestone1_1->setName('Backend API Development');
        $milestone1_1->setDescription('Set up Symfony backend with API Platform, authentication, and core entities.');
        $milestone1_1->setStatus(MilestoneStatus::COMPLETED);
        $milestone1_1->setDueDate(new \DateTimeImmutable('-10 days'));
        $manager->persist($milestone1_1);

        // Milestone 1_1 Tasks
        $task1 = $this->createTask($manager, $milestone1_1, 'Set up Symfony project', 'Initialize Symfony project with API Platform and configure Docker environment.', TaskStatus::COMPLETED, TaskPriority::HIGH, 0, $testUser);
        $task2 = $this->createTask($manager, $milestone1_1, 'Create User entity and authentication', 'Implement JWT authentication with LexikJWTAuthenticationBundle.', TaskStatus::COMPLETED, TaskPriority::HIGH, 1, $johnDoe);
        $task3 = $this->createTask($manager, $milestone1_1, 'Create Product entity', 'Product with name, description, price, SKU, inventory count.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 2, $johnDoe);
        $task4 = $this->createTask($manager, $milestone1_1, 'Create Category entity', 'Categories with hierarchical structure for products.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 3, $janeSmith);
        $task5 = $this->createTask($manager, $milestone1_1, 'Create Order entity', 'Orders with line items, shipping address, payment status.', TaskStatus::COMPLETED, TaskPriority::HIGH, 4, $testUser);

        // Project 1 - Milestone 2: Frontend Development
        $milestone1_2 = new Milestone();
        $milestone1_2->setProject($project1);
        $milestone1_2->setName('Frontend Development');
        $milestone1_2->setDescription('Build React frontend with product listing, cart, and checkout flow.');
        $milestone1_2->setStatus(MilestoneStatus::OPEN);
        $milestone1_2->setDueDate(new \DateTimeImmutable('+20 days'));
        $manager->persist($milestone1_2);

        // Milestone 1_2 Tasks
        $task6 = $this->createTask($manager, $milestone1_2, 'Set up React project with Vite', 'Initialize React project with TypeScript, Tailwind CSS, and React Router.', TaskStatus::COMPLETED, TaskPriority::HIGH, 0, $janeSmith);
        $task7 = $this->createTask($manager, $milestone1_2, 'Create product listing page', 'Grid view of products with filtering and sorting.', TaskStatus::IN_PROGRESS, TaskPriority::HIGH, 1, $janeSmith);
        $task8 = $this->createTask($manager, $milestone1_2, 'Create product detail page', 'Product details with images, description, add to cart button.', TaskStatus::IN_PROGRESS, TaskPriority::MEDIUM, 2, $johnDoe);
        $task9 = $this->createTask($manager, $milestone1_2, 'Implement shopping cart', 'Cart with add/remove items, quantity update, price calculation.', TaskStatus::TODO, TaskPriority::HIGH, 3, $janeSmith);
        $task10 = $this->createTask($manager, $milestone1_2, 'Build checkout flow', 'Multi-step checkout with address, payment, and confirmation.', TaskStatus::TODO, TaskPriority::HIGH, 4, null);
        $task11 = $this->createTask($manager, $milestone1_2, 'Create user account pages', 'Login, register, profile, order history pages.', TaskStatus::TODO, TaskPriority::MEDIUM, 5, null);

        // Project 1 - Milestone 3: Admin Panel
        $milestone1_3 = new Milestone();
        $milestone1_3->setProject($project1);
        $milestone1_3->setName('Admin Panel');
        $milestone1_3->setDescription('Build admin dashboard for managing products, orders, and users.');
        $milestone1_3->setStatus(MilestoneStatus::OPEN);
        $milestone1_3->setDueDate(new \DateTimeImmutable('+45 days'));
        $manager->persist($milestone1_3);

        // Milestone 1_3 Tasks
        $task12 = $this->createTask($manager, $milestone1_3, 'Create admin dashboard', 'Dashboard with sales stats, recent orders, low stock alerts.', TaskStatus::TODO, TaskPriority::MEDIUM, 0, null);
        $task13 = $this->createTask($manager, $milestone1_3, 'Product management CRUD', 'Add, edit, delete products with image upload.', TaskStatus::TODO, TaskPriority::HIGH, 1, null);
        $task14 = $this->createTask($manager, $milestone1_3, 'Order management', 'View orders, update status, process refunds.', TaskStatus::TODO, TaskPriority::HIGH, 2, null);
        $task15 = $this->createTask($manager, $milestone1_3, 'User management', 'View users, manage roles, disable accounts.', TaskStatus::TODO, TaskPriority::LOW, 3, null);

        // ============================================
        // PROJECT 2: Mobile Banking App
        // ============================================
        $project2 = new Project();
        $project2->setName('Mobile Banking App');
        $project2->setDescription('Secure mobile banking application with account management, transfers, bill payments, and budgeting tools.');
        $project2->setStatus(ProjectStatus::ACTIVE);
        $project2->setOwner($adminUser);
        $project2->setStartDate(new \DateTimeImmutable('-15 days'));
        $project2->setEndDate(new \DateTimeImmutable('+90 days'));
        $manager->persist($project2);

        // Project 2 Members
        $member4 = new ProjectMember();
        $member4->setProject($project2);
        $member4->setUser($adminUser);
        $member4->setRole(ProjectRole::ADMIN);
        $manager->persist($member4);

        $member5 = new ProjectMember();
        $member5->setProject($project2);
        $member5->setUser($testUser);
        $member5->setRole(ProjectRole::MEMBER);
        $manager->persist($member5);

        // Project 2 - Milestone 1: Security & Authentication
        $milestone2_1 = new Milestone();
        $milestone2_1->setProject($project2);
        $milestone2_1->setName('Security & Authentication');
        $milestone2_1->setDescription('Implement secure authentication with biometrics and 2FA.');
        $milestone2_1->setStatus(MilestoneStatus::OPEN);
        $milestone2_1->setDueDate(new \DateTimeImmutable('+15 days'));
        $manager->persist($milestone2_1);

        $task16 = $this->createTask($manager, $milestone2_1, 'Implement OAuth 2.0 authentication', 'Secure token-based authentication flow.', TaskStatus::IN_PROGRESS, TaskPriority::URGENT, 0, $adminUser);
        $task17 = $this->createTask($manager, $milestone2_1, 'Add biometric authentication', 'Face ID and fingerprint support for iOS and Android.', TaskStatus::TODO, TaskPriority::HIGH, 1, $testUser);
        $task18 = $this->createTask($manager, $milestone2_1, 'Implement 2FA via SMS/Email', 'Two-factor authentication for sensitive operations.', TaskStatus::TODO, TaskPriority::HIGH, 2, null);
        $task19 = $this->createTask($manager, $milestone2_1, 'Session management', 'Secure session handling with timeout and device tracking.', TaskStatus::IN_REVIEW, TaskPriority::MEDIUM, 3, $adminUser);

        // Project 2 - Milestone 2: Core Banking Features
        $milestone2_2 = new Milestone();
        $milestone2_2->setProject($project2);
        $milestone2_2->setName('Core Banking Features');
        $milestone2_2->setDescription('Account overview, transfers, and transaction history.');
        $milestone2_2->setStatus(MilestoneStatus::OPEN);
        $milestone2_2->setDueDate(new \DateTimeImmutable('+40 days'));
        $manager->persist($milestone2_2);

        $task20 = $this->createTask($manager, $milestone2_2, 'Account dashboard', 'Display account balances, recent transactions, quick actions.', TaskStatus::TODO, TaskPriority::HIGH, 0, null);
        $task21 = $this->createTask($manager, $milestone2_2, 'Internal transfers', 'Transfer between own accounts instantly.', TaskStatus::TODO, TaskPriority::HIGH, 1, null);
        $task22 = $this->createTask($manager, $milestone2_2, 'External transfers', 'Transfer to other banks with validation.', TaskStatus::TODO, TaskPriority::HIGH, 2, null);
        $task23 = $this->createTask($manager, $milestone2_2, 'Transaction history', 'Searchable transaction history with filters and export.', TaskStatus::TODO, TaskPriority::MEDIUM, 3, null);

        // ============================================
        // PROJECT 3: Company Website Redesign
        // ============================================
        $project3 = new Project();
        $project3->setName('Company Website Redesign');
        $project3->setDescription('Complete redesign of the corporate website with modern UI, improved UX, and better performance.');
        $project3->setStatus(ProjectStatus::ON_HOLD);
        $project3->setOwner($testUser);
        $project3->setStartDate(new \DateTimeImmutable('-60 days'));
        $project3->setEndDate(new \DateTimeImmutable('+30 days'));
        $manager->persist($project3);

        $member6 = new ProjectMember();
        $member6->setProject($project3);
        $member6->setUser($testUser);
        $member6->setRole(ProjectRole::ADMIN);
        $manager->persist($member6);

        // Project 3 - Milestone 1: Design Phase
        $milestone3_1 = new Milestone();
        $milestone3_1->setProject($project3);
        $milestone3_1->setName('Design Phase');
        $milestone3_1->setDescription('Create wireframes, mockups, and design system.');
        $milestone3_1->setStatus(MilestoneStatus::COMPLETED);
        $milestone3_1->setDueDate(new \DateTimeImmutable('-30 days'));
        $manager->persist($milestone3_1);

        $task24 = $this->createTask($manager, $milestone3_1, 'Competitor analysis', 'Research competitor websites and identify best practices.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 0, $testUser);
        $task25 = $this->createTask($manager, $milestone3_1, 'Create wireframes', 'Low-fidelity wireframes for all main pages.', TaskStatus::COMPLETED, TaskPriority::HIGH, 1, $testUser);
        $task26 = $this->createTask($manager, $milestone3_1, 'Design mockups in Figma', 'High-fidelity designs with responsive breakpoints.', TaskStatus::COMPLETED, TaskPriority::HIGH, 2, $testUser);
        $task27 = $this->createTask($manager, $milestone3_1, 'Create design system', 'Colors, typography, components, and guidelines.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 3, $testUser);

        // Project 3 - Milestone 2: Development (on hold)
        $milestone3_2 = new Milestone();
        $milestone3_2->setProject($project3);
        $milestone3_2->setName('Development');
        $milestone3_2->setDescription('Build the new website using Next.js.');
        $milestone3_2->setStatus(MilestoneStatus::OPEN);
        $milestone3_2->setDueDate(new \DateTimeImmutable('+20 days'));
        $manager->persist($milestone3_2);

        $task28 = $this->createTask($manager, $milestone3_2, 'Set up Next.js project', 'Initialize project with TypeScript and Tailwind.', TaskStatus::COMPLETED, TaskPriority::HIGH, 0, $testUser);
        $task29 = $this->createTask($manager, $milestone3_2, 'Build homepage', 'Hero section, features, testimonials, CTA.', TaskStatus::IN_PROGRESS, TaskPriority::HIGH, 1, $testUser);
        $task30 = $this->createTask($manager, $milestone3_2, 'Build about page', 'Company story, team, values.', TaskStatus::TODO, TaskPriority::MEDIUM, 2, null);
        $task31 = $this->createTask($manager, $milestone3_2, 'Build services page', 'Service offerings with details.', TaskStatus::TODO, TaskPriority::MEDIUM, 3, null);
        $task32 = $this->createTask($manager, $milestone3_2, 'Build contact page', 'Contact form with validation and map.', TaskStatus::TODO, TaskPriority::LOW, 4, null);

        // ============================================
        // PROJECT 4: Internal HR System
        // ============================================
        $project4 = new Project();
        $project4->setName('Internal HR System');
        $project4->setDescription('Employee management system with leave requests, timesheets, performance reviews, and payroll integration.');
        $project4->setStatus(ProjectStatus::COMPLETED);
        $project4->setOwner($adminUser);
        $project4->setStartDate(new \DateTimeImmutable('-120 days'));
        $project4->setEndDate(new \DateTimeImmutable('-10 days'));
        $manager->persist($project4);

        $member7 = new ProjectMember();
        $member7->setProject($project4);
        $member7->setUser($adminUser);
        $member7->setRole(ProjectRole::ADMIN);
        $manager->persist($member7);

        $member8 = new ProjectMember();
        $member8->setProject($project4);
        $member8->setUser($johnDoe);
        $member8->setRole(ProjectRole::MEMBER);
        $manager->persist($member8);

        // Project 4 - Milestone 1: Employee Management (completed)
        $milestone4_1 = new Milestone();
        $milestone4_1->setProject($project4);
        $milestone4_1->setName('Employee Management');
        $milestone4_1->setDescription('Core employee records and organizational structure.');
        $milestone4_1->setStatus(MilestoneStatus::COMPLETED);
        $milestone4_1->setDueDate(new \DateTimeImmutable('-60 days'));
        $manager->persist($milestone4_1);

        $task33 = $this->createTask($manager, $milestone4_1, 'Employee database schema', 'Design database for employee records.', TaskStatus::COMPLETED, TaskPriority::HIGH, 0, $adminUser);
        $task34 = $this->createTask($manager, $milestone4_1, 'Employee CRUD operations', 'Add, edit, view, archive employees.', TaskStatus::COMPLETED, TaskPriority::HIGH, 1, $johnDoe);
        $task35 = $this->createTask($manager, $milestone4_1, 'Department management', 'Create and manage departments.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 2, $johnDoe);
        $task36 = $this->createTask($manager, $milestone4_1, 'Org chart visualization', 'Interactive organizational chart.', TaskStatus::COMPLETED, TaskPriority::LOW, 3, $adminUser);

        // Project 4 - Milestone 2: Leave Management (completed)
        $milestone4_2 = new Milestone();
        $milestone4_2->setProject($project4);
        $milestone4_2->setName('Leave Management');
        $milestone4_2->setDescription('Leave requests, approvals, and balance tracking.');
        $milestone4_2->setStatus(MilestoneStatus::COMPLETED);
        $milestone4_2->setDueDate(new \DateTimeImmutable('-20 days'));
        $manager->persist($milestone4_2);

        $task37 = $this->createTask($manager, $milestone4_2, 'Leave request form', 'Submit leave requests with date range and type.', TaskStatus::COMPLETED, TaskPriority::HIGH, 0, $johnDoe);
        $task38 = $this->createTask($manager, $milestone4_2, 'Approval workflow', 'Manager approval with email notifications.', TaskStatus::COMPLETED, TaskPriority::HIGH, 1, $adminUser);
        $task39 = $this->createTask($manager, $milestone4_2, 'Leave balance tracking', 'Track and display leave balances by type.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 2, $johnDoe);
        $task40 = $this->createTask($manager, $milestone4_2, 'Team calendar view', 'Calendar showing team availability.', TaskStatus::COMPLETED, TaskPriority::MEDIUM, 3, $adminUser);

        // Add some comments to tasks
        $this->createComment($manager, $task7, $janeSmith, 'Started working on the product grid layout. Using CSS Grid for responsive design.');
        $this->createComment($manager, $task7, $johnDoe, 'Looks great! Should we add infinite scroll or pagination?');
        $this->createComment($manager, $task7, $janeSmith, 'I think pagination would be better for SEO. Will implement that.');

        $this->createComment($manager, $task16, $adminUser, 'Using Auth0 for OAuth implementation. Setting up the tenant now.');
        $this->createComment($manager, $task16, $testUser, 'Make sure to configure proper PKCE flow for mobile apps.');

        $this->createComment($manager, $task29, $testUser, 'Hero animation is causing layout shift. Need to fix CLS score.');

        $manager->flush();
    }

    private function createTask(
        ObjectManager $manager,
        Milestone $milestone,
        string $title,
        string $description,
        TaskStatus $status,
        TaskPriority $priority,
        int $position,
        ?User $assignee
    ): Task {
        $task = new Task();
        $task->setMilestone($milestone);
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setPosition($position);

        // Set due date based on status
        if ($status === TaskStatus::COMPLETED) {
            $task->setDueDate(new \DateTimeImmutable('-' . rand(5, 30) . ' days'));
        } else {
            $task->setDueDate(new \DateTimeImmutable('+' . rand(5, 30) . ' days'));
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

    private function createComment(
        ObjectManager $manager,
        Task $task,
        User $author,
        string $content
    ): Comment {
        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($author);
        $comment->setContent($content);
        $manager->persist($comment);

        return $comment;
    }
}

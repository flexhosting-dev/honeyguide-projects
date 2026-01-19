<?php

namespace App\Command;

use App\Entity\Comment;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-dummy-comments',
    description: 'Add dummy comments to some tasks for testing',
)]
class AddDummyCommentsCommand extends Command
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tasks = $this->taskRepository->findAll();
        $users = $this->userRepository->findAll();

        if (empty($tasks)) {
            $io->warning('No tasks found.');
            return Command::SUCCESS;
        }

        if (empty($users)) {
            $io->warning('No users found.');
            return Command::SUCCESS;
        }

        $dummyComments = [
            'Great progress on this task!',
            'Can we get an update on the status?',
            'I have some questions about the requirements.',
            'This looks good, let me review it.',
            'Need more information to proceed.',
            'Almost done with this one!',
            'Blocked by another task, will resume soon.',
            'Updated the implementation as discussed.',
        ];

        $commentsAdded = 0;

        // Add comments to roughly half of the tasks (random selection)
        foreach ($tasks as $index => $task) {
            // Skip some tasks so not all have comments
            if ($index % 2 === 0) {
                continue;
            }

            // Add 1-3 comments per task
            $numComments = rand(1, 3);

            for ($i = 0; $i < $numComments; $i++) {
                $comment = new Comment();
                $comment->setTask($task);
                $comment->setAuthor($users[array_rand($users)]);
                $comment->setContent($dummyComments[array_rand($dummyComments)]);

                $this->entityManager->persist($comment);
                $commentsAdded++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Added %d dummy comments to tasks.', $commentsAdded));

        return Command::SUCCESS;
    }
}

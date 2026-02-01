<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Form\CommentFormType;
use App\Repository\TaskRepository;
use App\Enum\NotificationType;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/tasks/{taskId}/comments', name: 'app_comment_add', methods: ['POST'])]
    public function add(Request $request, string $taskId): Response
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Task not found');
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();

        // Handle AJAX requests with JSON body
        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            $content = trim($data['content'] ?? '');

            if (empty($content)) {
                return $this->json(['error' => 'Comment content is required'], Response::HTTP_BAD_REQUEST);
            }

            $mentionedUserIds = $data['mentionedUserIds'] ?? null;

            $comment = new Comment();
            $comment->setTask($task);
            $comment->setAuthor($user);
            $comment->setContent($content);
            $comment->setMentionedUserIds($mentionedUserIds);

            $this->entityManager->persist($comment);

            $this->activityService->logCommentAdded(
                $project,
                $user,
                $task->getId(),
                $task->getTitle()
            );

            // Notify task assignees about the comment
            foreach ($task->getAssignees() as $assignee) {
                $this->notificationService->notify(
                    $assignee->getUser(),
                    NotificationType::COMMENT_ADDED,
                    $user,
                    'task',
                    $task->getId(),
                    $task->getTitle(),
                );
            }

            // Notify mentioned users
            if ($mentionedUserIds) {
                foreach ($mentionedUserIds as $mentionedUserId) {
                    $mentionedUser = $this->userRepository->find($mentionedUserId);
                    if ($mentionedUser) {
                        $this->notificationService->notify(
                            $mentionedUser,
                            NotificationType::MENTIONED,
                            $user,
                            'task',
                            $task->getId(),
                            $task->getTitle(),
                        );
                    }
                }
            }

            $this->entityManager->flush();

            $author = $comment->getAuthor();
            $basePath = $request->getBasePath();
            return $this->json([
                'success' => true,
                'comment' => [
                    'id' => $comment->getId()->toString(),
                    'content' => $comment->getContent(),
                    'authorName' => $author->getFullName(),
                    'authorInitials' => strtoupper(substr($author->getFirstName(), 0, 1)),
                    'authorAvatar' => $author->getAvatar() ? $basePath . '/uploads/avatars/' . $author->getAvatar() : '',
                    'createdAt' => $comment->getCreatedAt()->format('M d, H:i'),
                ],
            ]);
        }

        // Handle traditional form submission
        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($user);

        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($comment);

            $this->activityService->logCommentAdded(
                $project,
                $user,
                $task->getId(),
                $task->getTitle()
            );

            $this->entityManager->flush();

            $this->addFlash('success', 'Comment added.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/comments/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    public function edit(Request $request, Comment $comment): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($comment->getAuthor()->getId()->toString() !== $user->getId()->toString()) {
            throw $this->createAccessDeniedException('You can only edit your own comments.');
        }

        $content = $request->request->get('content');
        if ($content) {
            $comment->setContent($content);
            $this->entityManager->flush();
            $this->addFlash('success', 'Comment updated.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $comment->getTask()->getId()]);
    }

    #[Route('/comments/{id}/delete', name: 'app_comment_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Comment $comment): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $comment->getTask();

        if ($comment->getAuthor()->getId()->toString() !== $user->getId()->toString()) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'You can only delete your own comments'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('You can only delete your own comments.');
        }

        // Handle AJAX requests
        if ($request->isXmlHttpRequest()) {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
            return $this->json(['success' => true]);
        }

        // Handle traditional form submission with CSRF
        if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
            $this->addFlash('success', 'Comment deleted.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}

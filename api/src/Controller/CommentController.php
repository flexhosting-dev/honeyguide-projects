<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Form\CommentFormType;
use App\Repository\TaskRepository;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
    ) {
    }

    #[Route('/tasks/{taskId}/comments', name: 'app_comment_add', methods: ['POST'])]
    public function add(Request $request, string $taskId): Response
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();

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

    #[Route('/comments/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(Request $request, Comment $comment): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $comment->getTask();

        if ($comment->getAuthor()->getId()->toString() !== $user->getId()->toString()) {
            throw $this->createAccessDeniedException('You can only delete your own comments.');
        }

        if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
            $this->addFlash('success', 'Comment deleted.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}

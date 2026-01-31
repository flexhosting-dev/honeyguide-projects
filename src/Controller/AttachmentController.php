<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\User;
use App\Repository\AttachmentRepository;
use App\Repository\TaskRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class AttachmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly TaskRepository $taskRepository,
        private readonly FileUploadService $fileUploadService,
    ) {
    }

    #[Route('/tasks/{taskId}/attachments', name: 'app_task_attachments_upload', methods: ['POST'])]
    public function uploadTaskAttachment(Request $request, string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();

        $uploadedFiles = $request->files->get('files');
        if (!$uploadedFiles) {
            return $this->json(['error' => 'No files uploaded'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        $attachments = [];
        $basePath = $request->getBasePath();

        foreach ($uploadedFiles as $file) {
            try {
                $fileData = $this->fileUploadService->upload($file);

                $attachment = new Attachment();
                $attachment->setOriginalName($fileData['originalName']);
                $attachment->setStoredName($fileData['storedName']);
                $attachment->setMimeType($fileData['mimeType']);
                $attachment->setFileSize($fileData['fileSize']);
                $attachment->setPath($fileData['path']);
                $attachment->setUploadedBy($user);
                $attachment->setAttachableType('task');
                $attachment->setAttachableId($task->getId());

                $this->entityManager->persist($attachment);

                $attachments[] = $this->serializeAttachment($attachment, $basePath);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'attachments' => $attachments]);
    }

    #[Route('/comments/{commentId}/attachments', name: 'app_comment_attachments_upload', methods: ['POST'])]
    public function uploadCommentAttachment(Request $request, string $commentId): JsonResponse
    {
        $comment = $this->entityManager->find(\App\Entity\Comment::class, $commentId);
        if (!$comment) {
            return $this->json(['error' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $comment->getTask()->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();

        $uploadedFiles = $request->files->get('files');
        if (!$uploadedFiles) {
            return $this->json(['error' => 'No files uploaded'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        $attachments = [];
        $basePath = $request->getBasePath();

        foreach ($uploadedFiles as $file) {
            try {
                $fileData = $this->fileUploadService->upload($file);

                $attachment = new Attachment();
                $attachment->setOriginalName($fileData['originalName']);
                $attachment->setStoredName($fileData['storedName']);
                $attachment->setMimeType($fileData['mimeType']);
                $attachment->setFileSize($fileData['fileSize']);
                $attachment->setPath($fileData['path']);
                $attachment->setUploadedBy($user);
                $attachment->setAttachableType('comment');
                $attachment->setAttachableId($comment->getId());

                $this->entityManager->persist($attachment);

                $attachments[] = $this->serializeAttachment($attachment, $basePath);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'attachments' => $attachments]);
    }

    #[Route('/tasks/{taskId}/attachments', name: 'app_task_attachments_list', methods: ['GET'])]
    public function listTaskAttachments(Request $request, string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        $attachments = $this->attachmentRepository->findByAttachable('task', $task->getId());
        $basePath = $request->getBasePath();

        return $this->json([
            'success' => true,
            'attachments' => array_map(fn(Attachment $a) => $this->serializeAttachment($a, $basePath), $attachments),
        ]);
    }

    #[Route('/tasks/{taskId}/comment-attachments', name: 'app_task_comment_attachments', methods: ['GET'])]
    public function listCommentAttachments(Request $request, string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('PROJECT_VIEW', $task->getProject());

        $basePath = $request->getBasePath();
        $commentIds = [];
        foreach ($task->getComments() as $comment) {
            $commentIds[] = $comment->getId();
        }

        $grouped = [];
        foreach ($commentIds as $commentId) {
            $attachments = $this->attachmentRepository->findByAttachable('comment', $commentId);
            if (!empty($attachments)) {
                $grouped[$commentId->toString()] = array_map(
                    fn(Attachment $a) => $this->serializeAttachment($a, $basePath),
                    $attachments
                );
            }
        }

        return $this->json(['success' => true, 'commentAttachments' => $grouped]);
    }

    #[Route('/attachments/{id}/download', name: 'app_attachment_download', methods: ['GET'])]
    public function download(Attachment $attachment): Response
    {
        $this->checkAttachmentAccess($attachment);

        $filePath = $this->fileUploadService->getAbsolutePath($attachment->getPath());
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getOriginalName()
        );

        return $response;
    }

    #[Route('/attachments/{id}/preview', name: 'app_attachment_preview', methods: ['GET'])]
    public function preview(Attachment $attachment): Response
    {
        $this->checkAttachmentAccess($attachment);

        $filePath = $this->fileUploadService->getAbsolutePath($attachment->getPath());
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $attachment->getOriginalName()
        );

        return $response;
    }

    #[Route('/attachments/{id}', name: 'app_attachment_delete', methods: ['DELETE'])]
    public function delete(Request $request, Attachment $attachment): JsonResponse
    {
        $this->checkAttachmentAccess($attachment);

        /** @var User $user */
        $user = $this->getUser();

        if ($attachment->getUploadedBy()->getId()->toString() !== $user->getId()->toString()) {
            return $this->json(['error' => 'You can only delete your own attachments'], Response::HTTP_FORBIDDEN);
        }

        $this->fileUploadService->delete($attachment->getPath());
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function checkAttachmentAccess(Attachment $attachment): void
    {
        $type = $attachment->getAttachableType();

        if ($type === 'task') {
            $task = $this->taskRepository->find($attachment->getAttachableId());
            if ($task) {
                $this->denyAccessUnlessGranted('PROJECT_VIEW', $task->getProject());
                return;
            }
        }

        if ($type === 'comment') {
            $comment = $this->entityManager->find(\App\Entity\Comment::class, $attachment->getAttachableId());
            if ($comment) {
                $this->denyAccessUnlessGranted('PROJECT_VIEW', $comment->getTask()->getProject());
                return;
            }
        }

        throw $this->createAccessDeniedException();
    }

    private function serializeAttachment(Attachment $attachment, string $basePath): array
    {
        return [
            'id' => $attachment->getId()->toString(),
            'originalName' => $attachment->getOriginalName(),
            'mimeType' => $attachment->getMimeType(),
            'fileSize' => $attachment->getFileSize(),
            'humanFileSize' => $attachment->getHumanFileSize(),
            'isImage' => $attachment->isImage(),
            'downloadUrl' => $basePath . '/attachments/' . $attachment->getId()->toString() . '/download',
            'previewUrl' => $attachment->isImage() ? $basePath . '/attachments/' . $attachment->getId()->toString() . '/preview' : null,
            'createdAt' => $attachment->getCreatedAt()->format('M d, H:i'),
        ];
    }
}

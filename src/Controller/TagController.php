<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TagController extends AbstractController
{
    // Predefined colors for new tags
    private const TAG_COLORS = [
        '#ef4444', // red
        '#f97316', // orange
        '#f59e0b', // amber
        '#eab308', // yellow
        '#84cc16', // lime
        '#22c55e', // green
        '#14b8a6', // teal
        '#06b6d4', // cyan
        '#0ea5e9', // sky
        '#3b82f6', // blue
        '#6366f1', // indigo
        '#8b5cf6', // violet
        '#a855f7', // purple
        '#d946ef', // fuchsia
        '#ec4899', // pink
        '#6b7280', // gray
    ];

    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/tags', name: 'app_tag_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tags = $this->tagRepository->findAll();

        return $this->json([
            'tags' => array_map(fn(Tag $tag) => $this->serializeTag($tag), $tags),
        ]);
    }

    #[Route('/tags/search', name: 'app_tag_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = min($request->query->getInt('limit', 10), 50);

        if (strlen($query) < 1) {
            return $this->json(['tags' => []]);
        }

        $tags = $this->tagRepository->search($query, $limit);

        return $this->json([
            'tags' => array_map(fn(Tag $tag) => $this->serializeTag($tag), $tags),
        ]);
    }

    #[Route('/tasks/{taskId}/tags', name: 'app_task_tag_add', methods: ['POST'])]
    public function addTagToTask(Request $request, string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $data = json_decode($request->getContent(), true);
        $tagId = $data['tagId'] ?? null;
        $tagName = trim($data['name'] ?? '');
        $tagColor = $data['color'] ?? null;

        // If tagId is provided, add existing tag
        if ($tagId) {
            $tag = $this->tagRepository->find($tagId);
            if (!$tag) {
                return $this->json(['error' => 'Tag not found'], Response::HTTP_NOT_FOUND);
            }

            if (!$task->hasTag($tag)) {
                $task->addTag($tag);
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'tag' => $this->serializeTag($tag),
            ]);
        }

        // Otherwise, create or find by name
        if (empty($tagName)) {
            return $this->json(['error' => 'Tag name is required'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($tagName) > 50) {
            return $this->json(['error' => 'Tag name must be at most 50 characters'], Response::HTTP_BAD_REQUEST);
        }

        // Validate color format if provided
        if ($tagColor && !preg_match('/^#[0-9A-Fa-f]{6}$/', $tagColor)) {
            $tagColor = null; // Invalid color, use random
        }

        // Check if tag exists
        $tag = $this->tagRepository->findByName($tagName);
        $isNewTag = false;

        if (!$tag) {
            // Create new tag
            $tag = new Tag();
            $tag->setName($tagName);
            $tag->setColor($tagColor ?? $this->getRandomColor());
            $tag->setCreatedBy($this->getUser());
            $this->entityManager->persist($tag);
            $isNewTag = true;
        }

        // Add tag to task if not already added
        $tagAdded = false;
        if (!$task->hasTag($tag)) {
            $task->addTag($tag);
            $tagAdded = true;
        }

        // Always flush if we made changes
        if ($isNewTag || $tagAdded) {
            $this->entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'tag' => $this->serializeTag($tag),
        ]);
    }

    #[Route('/tasks/{taskId}/tags/{tagId}', name: 'app_task_tag_remove', methods: ['DELETE'])]
    public function removeTagFromTask(string $taskId, string $tagId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $tag = $this->tagRepository->find($tagId);
        if (!$tag) {
            return $this->json(['error' => 'Tag not found'], Response::HTTP_NOT_FOUND);
        }

        $task->removeTag($tag);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/tasks/{taskId}/tags', name: 'app_task_tag_list', methods: ['GET'])]
    public function getTaskTags(string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        return $this->json([
            'tags' => array_map(fn(Tag $tag) => $this->serializeTag($tag), $task->getTags()->toArray()),
        ]);
    }

    #[Route('/tasks/{taskId}/tags/available', name: 'app_task_tag_available', methods: ['GET'])]
    public function getAvailableTags(string $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        // Return all available tags (global tags in this system)
        $tags = $this->tagRepository->findAll();

        return $this->json([
            'tags' => array_map(fn(Tag $tag) => $this->serializeTag($tag), $tags),
        ]);
    }

    private function serializeTag(Tag $tag): array
    {
        return [
            'id' => $tag->getId()->toString(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
        ];
    }

    private function getRandomColor(): string
    {
        return self::TAG_COLORS[array_rand(self::TAG_COLORS)];
    }
}

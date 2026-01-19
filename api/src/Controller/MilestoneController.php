<?php

namespace App\Controller;

use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\User;
use App\Form\MilestoneFormType;
use App\Repository\ProjectRepository;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects/{projectId}/milestones')]
class MilestoneController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
    ) {
    }

    #[Route('/new', name: 'app_milestone_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $projectId): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $milestone = new Milestone();
        $milestone->setProject($project);

        $form = $this->createForm(MilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($milestone);

            $this->activityService->logMilestoneCreated(
                $project,
                $user,
                $milestone->getId(),
                $milestone->getName()
            );

            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('milestone/new.html.twig', [
            'page_title' => 'New Milestone',
            'project' => $project,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_milestone_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $projectId, Milestone $milestone): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException('Milestone not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(MilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone updated successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('milestone/edit.html.twig', [
            'page_title' => 'Edit Milestone',
            'project' => $project,
            'milestone' => $milestone,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_milestone_delete', methods: ['POST'])]
    public function delete(Request $request, string $projectId, Milestone $milestone): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException('Milestone not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        if ($this->isCsrfTokenValid('delete' . $milestone->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($milestone);
            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone deleted successfully.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
}

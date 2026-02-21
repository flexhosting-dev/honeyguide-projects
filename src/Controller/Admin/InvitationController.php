<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\ProjectInvitationRepository;
use App\Service\ProjectInvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invitations')]
#[IsGranted('ROLE_ADMIN')]
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly ProjectInvitationRepository $invitationRepository,
        private readonly ProjectInvitationService $invitationService,
    ) {
    }

    #[Route('/pending', name: 'admin_invitations_pending', methods: ['GET'])]
    public function pending(): Response
    {
        $invitations = $this->invitationRepository->findPendingAdminApprovals();

        return $this->render('admin/invitations/pending.html.twig', [
            'invitations' => $invitations,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_invitation_approve', methods: ['POST'])]
    public function approve(Request $request, string $id): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $invitation = $this->invitationRepository->find($id);
        if (!$invitation) {
            throw $this->createNotFoundException('Invitation not found');
        }

        if ($this->isCsrfTokenValid('approve' . $invitation->getId(), $request->request->get('_token'))) {
            try {
                $this->invitationService->approveInvitation($invitation, $currentUser);
                $this->addFlash('success', 'Invitation approved and sent to ' . $invitation->getEmail());
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_invitations_pending');
    }

    #[Route('/{id}/decline', name: 'admin_invitation_decline', methods: ['POST'])]
    public function decline(Request $request, string $id): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $invitation = $this->invitationRepository->find($id);
        if (!$invitation) {
            throw $this->createNotFoundException('Invitation not found');
        }

        if ($this->isCsrfTokenValid('decline' . $invitation->getId(), $request->request->get('_token'))) {
            try {
                $this->invitationService->declineInvitation($invitation, $currentUser);
                $this->addFlash('success', 'Invitation declined.');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_invitations_pending');
    }
}

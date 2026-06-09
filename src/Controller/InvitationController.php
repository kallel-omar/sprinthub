<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceInvitationRepository;
use App\Repository\WorkspaceMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvitationController extends AbstractController
{
    #[Route('/invite/accept/{token}', name: 'app_invitation_accept')]
    public function accept(
        string $token,
        WorkspaceInvitationRepository $invitationRepository,
        WorkspaceMemberRepository $workspaceMemberRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $invitation = $invitationRepository->findOneBy([
            'token' => $token,
            'status' => 'pending',
        ]);

        if (!$invitation) {
            $this->addFlash('danger', 'Invitation not found.');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($invitation->getExpiresAt() < new \DateTimeImmutable()) {
            $invitation->setStatus('expired');
            $entityManager->flush();

            $this->addFlash('danger', 'Invitation has expired.');

            return $this->redirectToRoute('app_dashboard');
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('warning', 'Please create an account or login to accept the invitation.');

            return $this->redirectToRoute('app_register', [
                'invite' => $token,
            ]);
        }

        if ($user->getEmail() !== $invitation->getEmail()) {
            $this->addFlash('danger', 'This invitation was sent to another email address.');

            return $this->redirectToRoute('app_dashboard');
        }

        $existingMember = $workspaceMemberRepository->findOneBy([
            'workspace' => $invitation->getWorkspace(),
            'user' => $user,
        ]);

        if (!$existingMember) {
            $member = new WorkspaceMember();
            $member->setWorkspace($invitation->getWorkspace());
            $member->setUser($user);
            $member->setRole('member');

            $entityManager->persist($member);
        }

        $invitation->setStatus('accepted');

        $entityManager->flush();

        $this->addFlash('success', 'You have successfully joined the workspace.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $invitation->getWorkspace()->getId(),
        ]);
    }
}
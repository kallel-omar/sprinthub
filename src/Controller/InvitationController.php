<?php

namespace App\Controller;

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
            throw $this->createNotFoundException('Invitation not found.');
        }

        if ($invitation->getExpiresAt() < new \DateTimeImmutable()) {
            $invitation->setStatus('expired');
            $entityManager->flush();

            throw $this->createAccessDeniedException('Invitation has expired.');
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();

        if ($user->getEmail() !== $invitation->getEmail()) {
            throw $this->createAccessDeniedException(
                'This invitation was sent to another email address.'
            );
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

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $invitation->getWorkspace()->getId(),
        ]);
    }
}
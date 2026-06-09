<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkspaceMember;
use App\Form\RegistrationFormType;
use App\Repository\WorkspaceInvitationRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        WorkspaceInvitationRepository $invitationRepository
    ): Response {
        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable());

        $inviteToken = $request->query->get('invite');
        $emailLocked = false;
        $invitation = null;

        if ($inviteToken) {
            $invitation = $invitationRepository->findOneBy([
                'token' => $inviteToken,
                'status' => 'pending',
            ]);

            if ($invitation) {
                $user->setEmail($invitation->getEmail());
                $emailLocked = true;
            }
        }

        $form = $this->createForm(RegistrationFormType::class, $user, [
            'email_locked' => $emailLocked,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            $entityManager->persist($user);

            if ($invitation && $user->getEmail() === $invitation->getEmail()) {
                $member = new WorkspaceMember();
                $member->setWorkspace($invitation->getWorkspace());
                $member->setUser($user);
                $member->setRole('member');

                $entityManager->persist($member);

                $invitation->setStatus('accepted');

                $this->addFlash('success', 'Account created and workspace joined successfully.');
            } else {
                $this->addFlash('success', 'Account created successfully. Welcome to SprintHub.');
            }

            $entityManager->flush();

            return $security->login($user, LoginFormAuthenticator::class, 'main');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Please check the registration form and try again.');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
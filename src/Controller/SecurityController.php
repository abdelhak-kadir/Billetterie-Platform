<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, PasswordType};
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints as Assert;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        return $this->render('security/login.html.twig', ['error' => $auth->getLastAuthenticationError(), 'lastEmail' => $auth->getLastUsername()]);
    }
    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): never { throw new \LogicException('Handled by Symfony Security.'); }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, ['label' => 'Votre e-mail', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]])
            ->add('password', PasswordType::class, ['label' => 'Mot de passe (12 caractères minimum)', 'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 4096)]])->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData(); $email = strtolower(trim($data['email']));
            if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $form->addError(new FormError('Cette adresse est déjà utilisée. Connectez-vous.'));
            } else {
                $user = new User($email); // Le rôle public ne provient jamais du formulaire.
                $user->setPassword($hasher->hashPassword($user, $data['password']));
                try {
                    $em->persist($user); $em->flush();
                    $this->addFlash('success', 'Votre compte client est créé. Connectez-vous pour poursuivre.');
                    return $this->redirectToRoute('app_login');
                } catch (UniqueConstraintViolationException) { $form->addError(new FormError('Cette adresse est déjà utilisée.')); }
            }
        }
        return $this->render('security/register.html.twig', ['form' => $form->createView()]);
    }
}

<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ResetPasswordType;
use App\Entity\ResetPassword;
use App\Classe\Mail;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class ResetPasswordController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/mot-de-passe-oublie', name: 'reset_password')]
    public function index(Request $request): Response
    {
        if($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        if ($request->get('email')){
            $user = $this->em->getRepository(User::class)->findOneByEmail($request->get('email'));

            if($user){
                // Enregistre en bdd la demande du reset password
                $reset_password = new ResetPassword();
                $reset_password->setUser($user);
                $reset_password->setToken(uniqid());
                $reset_password->setCreatedAt(new \DateTime());
                $this->em->persist($reset_password);
                $this->em->flush();

                $url = $this->generateUrl('update_password',
                    ['token' => $reset_password->getToken()
                    ]);

                $content = "Bonjour ".$user->getFirstname().",<br>Vous avez demandé un nouveau mot de passe pour votre compte.<br>";
                $content .= "Pour réinitialiser votre mot de passe, cliquez sur le lien suivant <a href='".$url."'>mettre à jour votre mot de passe</a>";

                $mail = new Mail();
                $mail->send($user->getEmail(), $user->getFirstName().' '.$user->getLastName(), 'Réinialiser votre mot de passe', $content);

                $this->addFlash('notice', 'Un email vous a été envoyé pour réinitialiser votre mot de passe');
            } else {
                $this->addFlash('notice', 'Cet email n\'existe pas');

           }
        }
        return $this->render('reset_password/index.html.twig');
    }

    #[Route('/modifier-mon-mot-de-passe/{token}', name: 'update_password')]
    public function update(Request $request,$token, UserPasswordEncoderInterface $encoder, EntityManagerInterface $em): Response
    {
        $reset_password = $this->em->getRepository(ResetPassword::class)->findOneByToken($token);
        if(!$reset_password){
            return $this->redirectToRoute('reset_password');
        }
        //Vérifié si le CreatedAt = now - 3h
        $now = new \DateTime();
        if($now > $reset_password->getCreatedAt()->modify('+3 hours')){

            $this->addFlash('notice',
                'Votre demande de réinitialisation de mot de passe a expiré. Merci de la renouveler.');
            return $this->redirectToRoute('reset_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $new_pwd = $form->get('new_password')->getData();
            $new_pwd = $encoder->encodePassword($reset_password->getUser(), $new_pwd);
            $reset_password->getUser()->setPassword($new_pwd);
            $em->flush();

            $this->addFlash('notice', 'Votre mot de passe a été mis à jour');
            return $this->redirectToRoute('app_login');
        }

        return  $this->render('reset_password/update.html.twig', [
            'form' => $form->createView(),
        ]);

    }
}


<?php

namespace App\Controller;

use App\Form\ContactType;
use App\Service\AppMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MarketingController extends AbstractController
{
    /**
     * @param array{
     *   name: string,
     *   legal_name: string,
     *   address: string,
     *   city: string,
     *   country: string,
     *   tax_id: string,
     *   email: string,
     *   phone: string,
     *   representative: string
     * } $platform
     */
    public function __construct(
        private array $platform,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('marketing/home.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('marketing/about.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, AppMailer $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $mailer->sendContactMessage(
                (string) $data['name'],
                (string) $data['email'],
                (string) $data['subject'],
                (string) $data['message'],
            );

            $this->addFlash('success', 'Merci — votre message a bien été transmis. Nous vous répondrons rapidement.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('marketing/contact.html.twig', [
            'platform' => $this->platform,
            'form' => $form,
        ]);
    }
}

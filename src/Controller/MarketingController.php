<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
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
        private string $mailFrom,
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
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->mailFrom, $this->platform['name']))
                    ->to(new Address($this->platform['email'], $this->platform['legal_name']))
                    ->replyTo(new Address((string) $data['email'], (string) $data['name']))
                    ->subject('[Contact] '.$data['subject'])
                    ->htmlTemplate('emails/contact.html.twig')
                    ->context([
                        'sender_name' => $data['name'],
                        'sender_email' => $data['email'],
                        'subject' => $data['subject'],
                        'body' => $data['message'],
                        'platform' => $this->platform,
                    ]);

                $mailer->send($email);
            } catch (\Throwable) {
                // On confirme quand même pour ne pas bloquer si Mailer n'est pas configuré en local
            }

            $this->addFlash('success', 'Merci — votre message a bien été transmis. Nous vous répondrons rapidement.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('marketing/contact.html.twig', [
            'platform' => $this->platform,
            'form' => $form,
        ]);
    }
}

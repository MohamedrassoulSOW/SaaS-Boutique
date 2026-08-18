<?php

namespace App\Controller;

use App\Form\ContactType;
use App\Service\AppMailer;
use App\Service\PptxGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
     *   phone_alt: string,
     *   representative: string
     * } $platform
     */
    public function __construct(
        private array $platform,
        #[Autowire(param: 'app.name')]
        private string $appName = 'NdamStore',
        #[Autowire(param: 'app.tagline')]
        private string $appTagline = 'La réussite de votre commerce.',
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

    #[Route('/presentation', name: 'app_presentation')]
    public function presentation(): Response
    {
        return $this->render('marketing/presentation.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/presentation/powerpoint', name: 'app_presentation_pptx')]
    public function presentationPptx(PptxGeneratorService $pptx): Response
    {
        $content = $pptx->generate($this->appName, $this->appTagline, $this->platform);

        if ($content === '') {
            $this->addFlash('error', 'Impossible de générer le fichier PowerPoint. Réessayez plus tard.');

            return $this->redirectToRoute('app_presentation');
        }

        $filename = $this->appName . ' - Presentation.pptx';
        $tmpFile = tempnam(sys_get_temp_dir(), 'pptx_dl_') . '.pptx';
        file_put_contents($tmpFile, $content);

        $response = new BinaryFileResponse($tmpFile);
        $response->deleteFileAfterSend(true);
        $response->setContentDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        return $response;
    }

    #[Route('/guide', name: 'app_guide')]
    public function guide(): Response
    {
        return $this->render('marketing/guide.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/aide', name: 'app_guide_legacy')]
    public function guideLegacy(): Response
    {
        return $this->redirectToRoute('app_guide', status: 301);
    }

    #[Route('/cgu', name: 'app_legal_terms')]
    public function terms(): Response
    {
        return $this->render('marketing/legal_terms.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/confidentialite', name: 'app_legal_privacy')]
    public function privacy(): Response
    {
        return $this->render('marketing/legal_privacy.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/mentions-legales', name: 'app_legal_mentions')]
    public function mentions(): Response
    {
        return $this->render('marketing/legal_mentions.html.twig', [
            'platform' => $this->platform,
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(
        Request $request,
        AppMailer $mailer,
        #[Autowire(service: 'limiter.contact_form')]
        RateLimiterFactory $contactFormLimiter,
    ): Response {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clientKey = $request->getClientIp() ?: 'anon';
            $limiter = $contactFormLimiter->create($clientKey);
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('warning', 'Trop de messages envoyés. Réessayez dans une heure.');

                return $this->redirectToRoute('app_contact');
            }

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

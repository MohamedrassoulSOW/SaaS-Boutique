<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Sale;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoicePdfService
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $em,
    ) {
    }

    public function generate(Sale $sale, string $type = Invoice::TYPE_INVOICE, bool $persist = true): string
    {
        $invoice = $sale->getInvoice();
        if ($persist && $invoice && $invoice->hasPdf() && $invoice->getType() === $type) {
            return (string) $invoice->getPdfData();
        }

        $html = $this->twig->render('invoice/pdf.html.twig', [
            'sale' => $sale,
            'invoice' => $invoice,
            'type' => $type,
            'shop' => $sale->getShop(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();

        if ($persist && $invoice) {
            $invoice->setType($type);
            $invoice->setPdfData($pdf);
            $this->em->flush();
        }

        return $pdf;
    }
}

<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Sale;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoicePdfService
{
    public function __construct(private Environment $twig)
    {
    }

    public function generate(Sale $sale, string $type = Invoice::TYPE_INVOICE): string
    {
        $html = $this->twig->render('invoice/pdf.html.twig', [
            'sale' => $sale,
            'invoice' => $sale->getInvoice(),
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

        return $dompdf->output();
    }
}

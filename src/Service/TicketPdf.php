<?php

declare(strict_types=1);
namespace App\Service;
use App\Entity\Billet;
use Dompdf\{Dompdf, Options};
use Endroid\QrCode\{QrCode, ErrorCorrectionLevel};
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class TicketPdf
{
    public function __construct(private Environment $twig, private TicketCode $codes, private UrlGeneratorInterface $urls, #[Autowire('%kernel.project_dir%')] private string $projectDir) {}
    public function render(Billet $ticket): string
    {
        $url = $this->urls->generate('counter_check_ticket', ['code' => $this->codes->encode($ticket)], UrlGeneratorInterface::ABSOLUTE_URL);
        $qr = (new SvgWriter())->write(new QrCode(data: $url, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 320, margin: 24))->getDataUri();
        $cache = $this->projectDir.'/var/pdf';
        if (!is_dir($cache) && !mkdir($cache, 0770, true) && !is_dir($cache)) { throw new \RuntimeException('Impossible de préparer le cache PDF.'); }
        $options = new Options(['isRemoteEnabled'=>false, 'isPhpEnabled'=>false, 'isJavascriptEnabled'=>false, 'defaultFont'=>'DejaVu Sans', 'tempDir'=>$cache, 'fontCache'=>$cache, 'chroot'=>$this->projectDir.'/public']);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('store/ticket.pdf.html.twig', ['ticket'=>$ticket, 'qr'=>$qr]), 'UTF-8');
        $pdf->setPaper('A5', 'landscape'); $pdf->render();
        return $pdf->output();
    }
}

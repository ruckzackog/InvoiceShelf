<?php

namespace App\Services\PDFDrivers;

use App\Rules\SafeRemoteUrl;
use App\Support\GotenbergHostPolicy;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Http\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GotenbergPDFResponse
{
    /** @var ResponseInterface */
    protected $response;

    public function __construct($stream)
    {
        $this->response = $stream;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        $output = $this->response->getBody();

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function output(): string
    {
        return $this->response->getBody()->getContents();
    }
}

class GotenbergPDFDriver
{
    /**
     * @param  array{footer?: string, margins?: array{0: string, 1: string, 2: string, 3: string}}  $options
     */
    public function loadView(string $viewname, array $options = []): GotenbergPDFResponse
    {
        return new GotenbergPDFResponse(Gotenberg::send($this->buildRequest($viewname, $options)));
    }

    /**
     * The multipart request for Gotenberg, without sending it.
     *
     * @param  array{footer?: string, margins?: array{0: string, 1: string, 2: string, 3: string}}  $options
     */
    public function buildRequest(string $viewname, array $options = []): RequestInterface
    {
        $papersize = explode(' ', config('pdf.connections.gotenberg.papersize'));
        if (count($papersize) != 2) {
            throw new \InvalidArgumentException('Invalid Gotenberg Papersize specified');
        }

        $host = config('pdf.connections.gotenberg.host');

        // Defense in depth against SSRF: a host that bypassed request-time
        // validation (env/seed/stale config, or DNS rebinding) must still not
        // be able to target internal/private addresses. The single exception is
        // the host the operator declared in GOTENBERG_ALLOWED_PRIVATE_HOST,
        // which is how a sidecar deployment is supported — see
        // GotenbergHostPolicy.
        if (! GotenbergHostPolicy::isExemptFromSafeRemoteUrl((string) $host)
            && ! SafeRemoteUrl::isSafe((string) $host)) {
            throw new \RuntimeException('Refusing to render PDF: unsafe Gotenberg host.');
        }

        // Margins default to 0 so templates can set them in CSS. A template with
        // a footer needs real margins: Chromium draws the footer inside them.
        $margins = $options['margins'] ?? [0, 0, 0, 0];

        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins(...$margins)
            ->paperSize($papersize[0], $papersize[1]);

        if (isset($options['footer'])) {
            $request->footer(Stream::string('footer.html', view($options['footer'])->render()));
        }

        return $request->html(
            Stream::string(
                'document.html',
                view($viewname)->render(),
            )
        );
    }
}

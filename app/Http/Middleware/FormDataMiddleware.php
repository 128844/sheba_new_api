<?php

namespace App\Http\Middleware;


use App\Helper\FormData as FormDataHandler;
use Symfony\Component\HttpFoundation\Request;

class FormDataMiddleware
{
    private $disallowMethods = [
        Request::METHOD_GET,
        Request::METHOD_HEAD,
        Request::METHOD_POST,
    ];

    public function handle($request, \Closure $next)
    {
        if ($request instanceof Request) {
            if (!in_array($request->getRealMethod(), $this->disallowMethods)) {
                $headers = $request->headers;
                $contentType = $headers->get('content-type');

                if (preg_match('/multipart\/form-data/', $contentType)) {
                    $content = $request->getContent();

                    $static = new FormDataHandler($content);

                    $request->request->add($static->inputs);

                    $request->files->add($static->files);
                }
            }
        }

        return $next($request);

    }

}
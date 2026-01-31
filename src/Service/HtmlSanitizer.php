<?php

namespace App\Service;

class HtmlSanitizer
{
    private \HTMLPurifier $purifier;

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,strike,h1,h2,h3,ul,ol,li,blockquote,a[href|target],hr,code,pre,span[class|data-user-id]');
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedClasses', ['mention']);
        $config->set('Cache.DefinitionImpl', null);
        $this->purifier = new \HTMLPurifier($config);
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = $this->purifier->purify($html);

        // Check if result is empty after purification
        $textOnly = strip_tags($clean);
        if (trim($textOnly) === '') {
            return null;
        }

        return $clean;
    }
}

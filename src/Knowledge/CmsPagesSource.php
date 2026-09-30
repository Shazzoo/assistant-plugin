<?php

namespace Shazzoo\Assistant\Knowledge;

use Shazzoo\ContentStudioCore\Models\Page;

/**
 * De actieve pagina's van het CMS, in elke taal: titel, adres en de tekst uit de blokken.
 */
class CmsPagesSource implements KnowledgeSource
{
    public function pages(): iterable
    {
        $pages = Page::query()
            ->where('is_active', true)
            ->orderBy('locale')
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $body = BlockText::from($page->content ?? []);

            if ($body === '') {
                continue;
            }

            yield new KnowledgePage(
                title: (string) $page->title,
                url: (string) parse_url(cms_route('page', ['slug' => $page->slug, 'locale' => $page->locale]), PHP_URL_PATH) ?: '/',
                body: $body,
                locale: $page->locale,
            );
        }
    }
}

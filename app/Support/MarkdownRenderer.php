<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Single source of truth for turning note Markdown into HTML.
 *
 * Used by the note reader view, the note cards (excerpt) and the editor's
 * live preview, so what you type is exactly what you get. Raw HTML inside
 * the body is escaped and unsafe link schemes are dropped, so note content
 * can never break out of the page.
 */
class MarkdownRenderer
{
    private static ?MarkdownConverter $converter = null;

    public static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $env = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]);
            $env->addExtension(new CommonMarkCoreExtension());
            // Tables, task lists, strikethrough, autolinks — the GFM set.
            $env->addExtension(new GithubFlavoredMarkdownExtension());
            $env->addExtension(new ExternalLinkExtension());

            self::$converter = new MarkdownConverter($env);
        }

        return self::$converter;
    }

    public static function toHtml(?string $markdown): string
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return '';
        }

        return (string) self::converter()->convert($markdown);
    }

    /**
     * Render and turn resolved tokens (@person, #label) into links.
     * $map is token => url, applied to text nodes only (never inside
     * pre/code blocks or tags).
     */
    public static function toHtmlLinked(?string $markdown, array $map): string
    {
        $html = self::toHtml($markdown);

        if ($map === []) {
            return $html;
        }

        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $inCode = false;

        foreach ($parts as &$part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                $tag = strtolower($part);
                if (str_starts_with($tag, '<pre') || str_starts_with($tag, '<code')) {
                    $inCode = true;
                } elseif (str_starts_with($tag, '</pre') || str_starts_with($tag, '</code')) {
                    $inCode = false;
                }
                continue;
            }
            if ($inCode) {
                continue;
            }
            $part = strtr($part, $map);
        }

        return implode('', $parts);
    }

    /**
     * Markdown → plain text for excerpts, counts and search.
     */
    public static function toPlainText(?string $markdown): string
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return '';
        }

        $html = self::toHtml($markdown);

        // Code blocks first (their text is content), then any remaining tag.
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]*([*_>`#\-]{1,4})[ \t]*/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
